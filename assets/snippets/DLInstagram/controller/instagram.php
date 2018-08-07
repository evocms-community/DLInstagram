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
        $cachetime = $this->getCFGDef('cachetime', 172800);

        try {
            $json = file_get_contents($apiurl . 'users/self/?access_token=' . $token);
            $json = json_decode($json, true);
            $user = $json['data'];
        } catch (\Exception $e) {
            echo $e->getMessage();
            die();
        }

        $cachename = $cachedir . '/' . $user['username'] . '_' . md5(serialize([$token, $display, $paginate])) . '.json';
        $url = $apiurl . 'users/self/media/recent?access_token=' . $token;
// TODO: 
        if (file_exists($cachename) && filemtime($cachename) + $cachetime > time()) {
            $data = json_decode(file_get_contents($cachename), true);
        } else {
            $data = [];

            do {
                try {
                    $json = file_get_contents($url);
                    $json = json_decode($json, true);
                } catch (\Exception $e) {
                    echo $e->getMessage();
                    die();
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
