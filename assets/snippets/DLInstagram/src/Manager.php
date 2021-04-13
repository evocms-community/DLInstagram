<?php

namespace EvolutionCMS\DLInstagram;

use Exception;

class Manager
{
    const TOKEN_VALID   = 0;
    const TOKEN_EMPTY   = 1;
    const TOKEN_INVALID = 2;
    const TOKEN_EXPIRED = 3;

    private $strings = [
        '',
        'token_empty',
        'token_invalid',
        'token_expired',
    ];

    protected $params = [];

    protected $data      = [];
    protected $apiUrl    = 'https://graph.instagram.com/';
    protected $imagesDir = 'assets/images/instagram';
    protected $token;
    protected $tokenStatus;
    protected $cacheName;

    public function __construct($params = [])
    {
        $this->params = $params;
        $this->modx   = EvolutionCMS();
        $this->token  = $params['token'];

        if (!empty($this->token) && $this->token != $this->modx->getConfig('dlinstagram_token')) {
            $this->saveToken($this->token, time() + 2592000);
        }
    }

    public function checkToken($token = null)
    {
        if (is_null($token)) {
            $token = $this->token;
        }

        if (empty($token)) {
            return $this->setTokenStatus(self::TOKEN_EMPTY);
        }

        $now = time();
        $expires_in = $this->modx->getConfig('dlinstagram_expires_in', $now);

        // если до окончания действия токена осталось
        // не больше 2-х дней - нужно его продлить
        if ($now + 172800 > $expires_in) {
            return $this->prolongToken($token);
        }

        return $this->setTokenStatus(self::TOKEN_VALID);
    }

    protected function prolongToken($token)
    {
        try {
            $json = $this->request($this->apiUrl . 'refresh_access_token?' . http_build_query([
                'grant_type'   => 'ig_refresh_token',
                'access_token' => $token,
            ]));

            if (empty($json['access_token'])) {
                throw new Exception('Token request failed!<br><pre>' . htmlentities(print_r($json, true)) . '</pre>');
            }
        } catch (Exception $e) {
            $this->modx->logEvent(0, 3, $e->getMessage(), 'DLInstagram');
            return $this->getTokenStatus();
        }

        $this->saveToken($json['access_token'], time() + $json['expires_in']);
        return $this->setTokenStatus(self::TOKEN_VALID);
    }

    public function setTokenStatus($status)
    {
        if ($status != $this->modx->getConfig('dlinstagram_token_status')) {
            $this->saveSetting('dlinstagram_token_status', $status);
            $this->modx->clearCache('full');
        }

        return $this->tokenStatus = $status;
    }

    public function getTokenStatus()
    {
        return $this->tokenStatus;
    }

    public function setToken($token)
    {
        if (!empty($token)) {
            $this->token = $token;
        }
    }

    protected function saveToken($token, $expires_in)
    {
        $db    = $this->modx->db;
        $table = $this->modx->getFullTablename('site_plugins');

        $plugin = $db->getRow($db->select('*', $table, "`name` = 'DLInstagram'"));

        if ($plugin) {
            $properties = json_decode($plugin['properties'], true);

            if ($properties) {
                if (!empty($properties['token'][0])) {
                    $properties['token'][0]['value'] = $token;
                }

                $db->update(['properties' => json_encode($properties, JSON_UNESCAPED_UNICODE)], $table, "`id` = '{$plugin['id']}'");
            }
        }

        foreach (['dlinstagram_token' => $token, 'dlinstagram_expires_in' => $expires_in] as $setting => $value) {
            $this->saveSetting($setting, $value);
        }
    }

    protected function saveSetting($setting, $value)
    {
        $table = $this->modx->getFullTablename('system_settings');
        $db    = $this->modx->db;

        $query = $db->select('*', $table, "`setting_name` = '{$setting}'");

        if ($db->getRecordCount($query) > 0) {
            $db->update([
                'setting_value' => $value,
            ], $table, "`setting_name` = '{$setting}'");
        } else {
            $db->insert([
                'setting_name'  => $setting,
                'setting_value' => $value,
            ], $table);
        }

        $this->modx->config[$setting] = $value;
    }

    public function isTokenInvalid()
    {
        return $this->tokenStatus != self::TOKEN_VALID;
    }

    public function renderDashboardWidget($params = [])
    {
        $tokenStatus = $this->modx->getConfig('dlinstagram_token_status', self::TOKEN_VALID);

        if ($tokenStatus == self::TOKEN_VALID) {
            return '';
        }

        return file_get_contents(__DIR__ . '/../templates/widget_' . $this->strings[$tokenStatus] . '.tpl');
    }

    protected function getCacheName()
    {
        if ($this->cacheName === null) {
            $this->cacheName = MODX_BASE_PATH . 'assets/cache/instagram.' . md5($this->token) . '.json.pageCache.php';
        }

        return $this->cacheName;
    }

    public function getProfileData()
    {
        if (!empty($this->data)) {
            return $this->data;
        }

        $data = [
            'user'      => null,
            'images'    => [],
            'timestamp' => (new \DateTime())->getTimestamp(),
        ];

        $cacheName = $this->getCacheName();

        if (file_exists($cacheName)) {
            $cached = json_decode(file_get_contents($cacheName), true);

            if (empty($cached['timestamp']) || !empty($cached['timestamp']) && $cached['timestamp'] + $this->params['cachetime'] < time()) {
                unlink($cacheName);
            } else {
                $data = $cached;
            }
        }

        if (empty($data['user'])) {
            $data['user'] = $this->getUserInfo();
        }

        $this->data = $data;
        return $data;
    }

    public function getUserInfo()
    {
        try {
            if (empty($this->token)) {
                throw new Exception('Token empty');
            }

            $json = $this->loadUserInfo($this->apiUrl . 'me?' . http_build_query([
                'fields' => $this->params['fetchUserFields'],
                'access_token' => $this->token,
            ]));
        } catch (Exception $e) {
            $this->modx->logEvent(0, 3, 'User request failed: ' . $e->getMessage(), 'DLInstagram');
            return [];
        }

        return $json;
    }

    public function getMedia($page, $display)
    {
        $data = $this->modx->instagram->getProfileData();

        $needCount = min($page * $display, $data['user']['media_count']);

        if (count($data['images']) < $needCount) {
            if (!empty($data['next_url'])) {
                $url = $data['next_url'];
            } else {
                $url = $this->apiUrl . $data['user']['id'] . '/media?' . http_build_query([
                    'fields' => $this->params['fetchMediaFields'],
                    'access_token' => $this->token,
                ]);
            }

            do {
                try {
                    if (empty($this->token)) {
                        throw new Exception('Token empty');
                    }

                    $json = $this->loadMedia($url);
                } catch (\Exception $e) {
                    $this->modx->logEvent(0, 3, 'Data request failed: ' . $e->getMessage(), 'DLInstagram');
                    return [];
                }

                $data['images'] = array_merge($data['images'], $this->prepareJsonData($json['data']));

                if (!empty($json['paging']['next'])) {
                    $data['next_url'] = $url = $json['paging']['next'];

                    if (count($data['images']) < $needCount) {
                        continue;
                    }
                }

                break;
            } while (true);

            file_put_contents($this->getCacheName(), json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        $this->data = $data;

        return array_slice($data['images'], ($page - 1) * $display, $display);
    }

    protected function prepareJsonData($data)
    {
        if (!is_dir(MODX_BASE_PATH . $this->imagesDir)) {
            mkdir(MODX_BASE_PATH . $this->imagesDir, 0744, true);
        }

        foreach ($data as &$media) {
            $media['timestamp'] = strtotime($media['timestamp']);
            $media['url']   = $media['permalink'];
            $media['image'] = $this->imagesDir . '/' . $media['id'] . '.jpg';
            $imageLocal     = MODX_BASE_PATH . $media['image'];

            if (!is_readable($imageLocal)) {
                if ($media['media_type'] == 'VIDEO') {
                    $imageUrl = $media['thumbnail_url'];
                } else {
                    $imageUrl = $media['media_url'];
                }

                $raw = file_get_contents($imageUrl);
                file_put_contents($imageLocal, $raw);
            }
        }

        unset($media);

        return $data;
    }

    protected function loadUserInfo($url)
    {
        $json = $this->request($url);

        if (empty($json['id'])) {
            throw new Exception('User id is empty!<br><pre>' . htmlentities(print_r($json, true)) . '</pre>');
        }

        return $json;
    }

    protected function loadMedia($url)
    {
        $json = $this->request($url);

        if (empty($json['data'])) {
            throw new Exception('Data section is empty!<br><pre>' . htmlentities(print_r($json, true)) . '</pre>');
        }

        return $json;
    }

    protected function request($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($result, true);

        if (!empty($this->params['debug'])) {
            $this->modx->logEvent(0, 1, 'Request:<br>' . $url . '<br><br>Response: ' . $code . '<br>' . htmlentities($result) . '<br><br><pre>' . htmlentities(print_r($json, true)) . '</pre>', 'DLInstagram');
        }

        if ($code != 200) {
            $status = self::TOKEN_INVALID;

            if (!empty($json)) {
                if (!empty($json['error']['message'])) {
                    if (strstr($json['error']['message'], 'Session has expired')) {
                        $status = self::TOKEN_EXPIRED;
                    }
                }
            }

            $this->setTokenStatus($status);

            throw new Exception($url . '<br>code ' . $code . (!empty($json) ? '<br><pre>' . htmlentities(print_r($json, true)) . '</pre>' : ''));
        }

        return $json;
    }
}
