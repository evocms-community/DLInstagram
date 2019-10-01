<?php

class instagramDocLister extends onetableDocLister
{
    protected $data = [];
    protected $apiUrl = 'https://api.instagram.com/v1/';
    protected $cacheDir;
    protected $token;
    protected $cacheName;

    public function getDocs($tvlist = '')
    {
        $this->cacheDir = MODX_BASE_PATH . 'assets/cache/instagram';

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0744, true);
        }

        $this->token = $this->getCFGDef('token');
        $this->cacheName = $this->cacheDir . '/' . md5($this->token) . '.json';

        if ($this->extPaginate = $this->getExtender('paginate')) {
            $this->extPaginate->init($this);
        }

        $this->_docs = $this->getDocList();
        return $this->_docs;
    }

    protected function getDocList()
    {
        $page      = 1;
        $paginate  = $this->getCFGDef('paginate', 0);
        $display   = $this->getCFGDef('display', 10);

        if ($this->extPaginate) {
            $page = $this->extPaginate->currentPage();
        }

        $data = $this->getProfileData();

        $needCount = min($page * $display, $data['user']['counts']['media']);

        if (count($data['images']) < $needCount) {
            if (!empty($data['next_url'])) {
                $url = $data['next_url'];
            } else {
                $url = $this->apiUrl . 'users/self/media/recent?access_token=' . $this->token;
            }

            do {
                try {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $result = curl_exec($ch);
                    $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    $json = json_decode($result, true);

                    if ($code != 200) {
                        if (!empty($json)) {
                            throw new Exception('code ' . $code . '<br><pre>' . htmlentities(print_r($json, true)) . '</pre>');
                        }

                        throw new Exception('code ' . $code);
                    }

                    if (empty($json['data'])) {
                        throw new Exception('Data section is empty!<br><pre>' . htmlentities(print_r($json, true)) . '</pre>');
                    }
                } catch (\Exception $e) {
                    $this->modx->logEvent(0, 3, 'Data request failed: ' . $e->getMessage(), 'DLInstagram');
                    return [];
                }

                $data['images'] = array_merge($data['images'], $json['data']);

                if (count($data['images']) < $needCount && !empty($json['pagination']['next_url'])) {
                    $data['next_url'] = $url = $json['pagination']['next_url'];
                    continue;
                }

                break;
            } while (true);
        }

        file_put_contents($this->cacheName, json_encode($data, JSON_UNESCAPED_UNICODE));
        $this->data = $data;

        return array_slice($data['images'], ($page - 1) * $display, $display);
    }

    protected function getProfileData()
    {
        if (!empty($this->data)) {
            return $this->data;
        }

        $data = [
            'user'      => [],
            'images'    => [],
            'timestamp' => (new DateTime())->getTimestamp(),
        ];

        if (file_exists($this->cacheName)) {
            $cached = json_decode(file_get_contents($this->cacheName), true);

            if (empty($cached['timestamp']) || !empty($cached['timestamp']) && $cached['timestamp'] + $this->getCFGDef('cachetime', 86400) < time()) {
                unlink($this->cacheName);
            } else {
                $data = $cached;
            }
        }

        if (empty($data['user'])) {
            $data['user'] = $this->loadUserInfo();
        }

        $this->data = $data;
        return $data;
    }

    protected function loadUserInfo()
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl . 'users/self/?access_token=' . $this->token);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($ch);
            $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $json = json_decode($result, true);

            if ($code != 200) {
                if (!empty($json)) {
                    throw new Exception('code ' . $code . '<br><pre>' . htmlentities(print_r($json, true)) . '</pre>');
                }

                throw new Exception('code ' . $code);
            }

            if (empty($json['data'])) {
                throw new Exception('Data section is empty!<br><pre>' . htmlentities(print_r($json, true)) . '</pre>');
            }
        } catch (Exception $e) {
            $this->modx->logEvent(0, 3, 'User request failed: ' . $e->getMessage(), 'DLInstagram');
            return [];
        }

        return $json['data'];
    }

    public function getChildrenCount()
    {
        $data = $this->getProfileData();
        return $data['user']['counts']['media'];
    }

    public function getChildrenFolder($id)
    {
        return [];
    }
}
