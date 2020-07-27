<?php

class instagramDocLister extends onetableDocLister
{
    protected $data      = [];
    protected $apiUrl    = 'https://graph.instagram.com/';
    protected $imagesDir = 'assets/images/instagram';
    protected $token;
    protected $cacheName;

    public function getDocs($tvlist = '')
    {
        if (!is_dir(MODX_BASE_PATH . $this->imagesDir)) {
            mkdir(MODX_BASE_PATH . $this->imagesDir, 0744, true);
        }

        $this->token = $this->getCFGDef('token');
        $this->cacheName = MODX_BASE_PATH . 'assets/cache/instagram.' . md5($this->token) . '.json.pageCache.php';

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

        $needCount = min($page * $display, $data['user']['media_count']);

        if (count($data['images']) < $needCount) {
            if (!empty($data['next_url'])) {
                $url = $data['next_url'];
            } else {
                $url = $this->apiUrl . $data['user']['id'] . '/media?fields=' . $this->getCFGDef('fetchMediaFields', 'caption,media_type,media_url,permalink,thumbnail_url,timestamp') . '&access_token=' . $this->token;
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
                    $this->modx->logEvent(0, 3, 'Data request failed<br>' . $url . '<br>' . $e->getMessage(), 'DLInstagram');
                    return [];
                }

                foreach ($json['data'] as &$media) {
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

                $data['images'] = array_merge($data['images'], $json['data']);

                if (!empty($json['paging']['next'])) {
                    $data['next_url'] = $url = $json['paging']['next'];

                    if (count($data['images']) < $needCount) {
                        continue;
                    }
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
            'user'      => null,
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
        $url = $this->apiUrl . 'me?fields=' . $this->getCFGDef('fetchUserFields', 'id,media_count,username') . '&access_token=' . $this->token;

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

            if (empty($json['id'])) {
                throw new Exception('User id is empty!<br><pre>' . htmlentities(print_r($json, true)) . '</pre>');
            }
        } catch (Exception $e) {
            $this->modx->logEvent(0, 3, 'User request failed<br>' . $url . '<br>' . $e->getMessage(), 'DLInstagram');
            return [];
        }

        return $json;
    }

    public function getChildrenCount()
    {
        $data = $this->getProfileData();
        return $data['user']['media_count'];
    }

    public function getChildrenFolder($id)
    {
        return [];
    }
}