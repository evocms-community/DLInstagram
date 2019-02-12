<?php

class instagramDocLister extends onetableDocLister
{
    public function getDocs($tvlist = '')
    {
        if ($this->extPaginate = $this->getExtender('paginate')) {
            $this->extPaginate->init($this);
        }

        $this->_docs = $this->getDocList();
        return $this->_docs;
    }

    protected function getDocList()
    {
        $apiurl = 'https://api.instagram.com/v1/';
        $cachedir = MODX_BASE_PATH . 'assets/cache/instagram';

        if (!is_dir($cachedir)) {
            mkdir($cachedir, 0744, true);
        }

        $token     = $this->getCFGDef('token');
        $paginate  = $this->getCFGDef('paginate', 0);
        $display   = $this->getCFGDef('display', 10);
        $cachetime = $this->getCFGDef('cachetime', 86400);

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiurl . 'users/self/?access_token=' . $token);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($ch);
            $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $json = json_decode($result, true);

            if ($code != 200) {
                if (!empty($json)) {
                    throw new Exception('code ' . $code . '<br><pre>' . print_r($json, true) . '</pre>');
                }

                throw new Exception('code ' . $code);
            }

            if (empty($json['data'])) {
                throw new Exception('Data section is empty!<br><pre>' . print_r($json, true) . '</pre>');
            }
        } catch (Exception $e) {
            $this->modx->logEvent(0, 3, 'User request failed: ' . $e->getMessage(), 'DLInstagram');
            return [];
        }

        $user = $json['data'];

        $cachename = $cachedir . '/' . $user['username'] . '_' . md5(serialize([$token, $display, $paginate])) . '.json';
        $url = $apiurl . 'users/self/media/recent?access_token=' . $token;
// TODO: pagination
        if (file_exists($cachename) && filemtime($cachename) + $cachetime > time()) {
            $data = json_decode(file_get_contents($cachename), true);
        } else {
            $data = [];

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
                            throw new Exception('code ' . $code . '<br><pre>' . print_r($json, true) . '</pre>');
                        }

                        throw new Exception('code ' . $code);
                    }

                    if (empty($json['data'])) {
                        throw new Exception('Data section is empty!<br><pre>' . print_r($json, true) . '</pre>');
                    }
                } catch (\Exception $e) {
                    $this->modx->logEvent(0, 3, 'Data request failed: ' . $e->getMessage(), 'DLInstagram');
                    return [];
                }

                $data = array_merge($data, $json['data']);

                if (count($data) < $display && !empty($json['pagination']['next_url'])) {
                    $url = $json['pagination']['next_url'];
                    continue;
                }

                break;
            } while (true);

            if (count($data) > $display) {
                $data = array_slice($data, 0, $display);
            }

            $data = [
                'user'   => $user,
                'images' => $data,
            ];

            file_put_contents($cachename, json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        return $data['images'];
    }

    public function getChildrenCount()
    {
        return count($this->_docs);
    }

    public function getChildrenFolder($id)
    {
        return [];
    }
}
