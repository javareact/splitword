<?php

/**
 * Created by PhpStorm.
 * User: Fuzzyy
 * Date: 2017/12/21
 * Time: 下午8:11
 */

namespace JavaReact\SplitWord;

/**
 * Class VicWord
 * @package JavaReact\SplitWord
 */
class VicWord
{
    /**
     *
     */
    const TYPE_JSON = 'json';
    /**
     *
     */
    const TYPE_IGB = 'igb';

    /**
     * @var array|mixed
     */
    private $dict = [];

    /**
     * @var string
     */
    private $end = '\\';

    /**
     * @var bool
     */
    private $auto = false;

    /**
     * @var int
     */
    private $count = 0;


    /**
     * @var string 词性
     */
    private $x = '\\x';

    /**
     * VicWord constructor.
     * @param string $type
     * @throws \Exception
     */
    public function __construct($type = self::TYPE_JSON)
    {
        define('_VIC_WORD_DICT_PATH_', __DIR__ . '/../Data/dict.' . $type);

        if (!file_exists(_VIC_WORD_DICT_PATH_)) {
            throw new \Exception('分词文件不存在');
        }
        switch ($type) {
            case self::TYPE_IGB:
                if (!function_exists('igbinary_unserialize')) {
                    throw new \Exception('需要安装igb扩展');
                }
                $this->dict = igbinary_unserialize(file_get_contents(_VIC_WORD_DICT_PATH_));
                break;
            case self::TYPE_JSON:
            default:
                $this->dict = json_decode(file_get_contents(_VIC_WORD_DICT_PATH_), true);
                break;
        }
    }

    /**
     * @param string $path
     * @return array
     */
    public function getWord($str)
    {
        $this->auto = false;
        $str        = $this->filter($str);
        return $this->find($str);
    }

    /**
     * @param string $path
     * @return array
     */
    public function getShortWord($str)
    {
        $this->auto = false;
        $str        = $this->filter($str);
        return $this->shortfind($str);
    }

    /**
     * @param string $path
     * @return array
     */
    public function getAutoWord($str)
    {
        $this->auto = true;
        $str        = $this->filter($str);
        return $this->autoFind($str, ['long' => 1]);
    }

    /**
     * @param $str
     * @return string
     */
    private function filter($str)
    {
        return strtolower(trim($str));
    }

    /**
     * @param $str
     * @param $i
     * @return array
     * @throws \Exception
     */
    private function getD(&$str, $i)
    {
        $o = ord($str[$i]);
        if ($o < 128) {
            $d = $str[$i];
        } else {
            $o = $o >> 4;
            if ($o == 12) {
                $d = $str[$i] . $str[++$i];
            } else if ($o === 14) {
                $d = $str[$i] . $str[++$i] . $str[++$i];
            } else if ($o == 15) {
                $d = $str[$i] . $str[++$i] . $str[++$i] . $str[++$i];
            } else {
                throw new \Exception('我不认识的编码');
            }
        }
        return [$d, $i];
    }

    /**
     * @param $str
     * @param array $auto_info
     * @return array
     */
    private function autoFind($str, $auto_info = [])
    {
        if ($auto_info['long']) {
            return $this->find($str, $auto_info);
        } else {
            return $this->shortfind($str, $auto_info);
        }
    }

    /**
     * @param $r
     * @param $auto_info
     * @return bool
     */
    private function reGet(&$r, $auto_info)
    {
        $auto_info['c'] = isset($auto_info['c']) ? $auto_info['c']++ : 1;
        $l              = count($r) - 1;
        $p              = [];
        $str            = '';
        for ($i = $l; $i >= 0; $i--) {
            $str = $r[$i][0] . $str;
            $f   = $r[$i][3];
            array_unshift($p, $r[$i]);
            unset($r[$i]);
            if ($f == 1) {
                break;
            }
        }
        $this->count++;
        $l = strlen($str);
        if (isset($r[$i - 1])) {
            $w = $r[$i - 1][1];
        } else {
            $w = 0;
        }
        if (isset($auto_info['pl']) && $l == $auto_info['pl']) {
            $r = $p;
            return false;
        } else if ($str && $auto_info['c'] < 3) {
            $auto_info['pl']   = $l;
            $auto_info['long'] = !$auto_info['long'];
            $sr                = $this->autoFind($str, $auto_info);
            $sr                = array_map(function ($v) use ($w) {
                $v[1] += $w;
                return $v;
            }, $sr);
            $r                 = array_merge($r, $this->getGoodWord($p, $sr));
        }
    }

    /**
     * @param $old
     * @param $new
     * @return mixed
     */
    private function getGoodWord($old, $new)
    {
        if (!$new) {
            return $old;
        }
        if ($this->getUnknowCount($old) > $this->getUnknowCount($new)) {
            return $new;
        } else {
            return $old;
        }

    }

    /**
     * @param $ar
     * @return int
     */
    private function getUnknowCount($ar)
    {
        $i = 0;
        foreach ($ar as $v) {
            if ($v[3] == 0) {
                $i += strlen($v[0]);
            }
        }
        return $i;
    }


    /**
     * @param $str
     * @param array $auto_info
     * @return array
     * @throws \Exception
     */
    private function find($str, $auto_info = [])
    {
        $len = strlen($str);
        $s   = '';
        $n   = '';
        $j   = 0;
        $r   = [];
        for ($i = 0; $i < $len; $i++) {
            list($d, $i) = $this->getD($str, $i);

            if (isset($wr[$d])) {
                $s  .= $d;
                $wr = $wr[$d];
            } else {
                if (isset($wr[$this->end])) {
                    $this->addNotFind($r, $n, $s, $j, $auto_info);
                    $this->addResult($r, $s, $j, $wr[$this->x]);
                    $n = '';
                }
                $wr = $this->dict;
                if (isset($wr[$d])) {
                    $s  = $d;
                    $wr = $wr[$d];
                } else {
                    $s = '';
                }
            }
            $n .= $d;
            $j = $i;

        }
        if (isset($wr[$this->end])) {
            $this->addNotFind($r, $n, $s, $i, $auto_info);
            $this->addResult($r, $s, $i, $wr[$this->x]);
        } else {
            $this->addNotFind($r, $n, '', $i, $auto_info);
        }

        return $r;
    }


    /**
     * @param $r
     * @param $n
     * @param $s
     * @param $i
     * @param array $auto_info
     */
    private function addNotFind(&$r, $n, $s, $i, $auto_info = [])
    {
        if ($n !== $s) {
            $n = str_replace($s, '', $n);
            $this->addResult($r, $n, $i - strlen($s), null, 0);
            if ($this->auto) {
                $this->reGet($r, $auto_info);
            }
        }
    }


    /**
     * @param $str
     * @param array $auto_info
     * @return array
     * @throws \Exception
     */
    private function shortFind($str, $auto_info = [])
    {
        $len = strlen($str);
        $s   = '';
        $n   = '';
        $r   = [];
        for ($i = 0; $i < $len; $i++) {
            $j = $i;
            list($d, $i) = $this->getD($str, $i);

            if (isset($wr[$d])) {
                $s  .= $d;
                $wr = $wr[$d];
            } else {
                if (isset($wr[$this->end])) {
                    $this->addNotFind($r, $n, $s, $j, $auto_info);
                    $this->addResult($r, $s, $j, $wr[$this->x]);
                    $n = '';
                }
                $wr = $this->dict;
                if (isset($wr[$d])) {
                    $s  = $d;
                    $wr = $wr[$d];
                } else {
                    $s = '';
                }
            }

            $n .= $d;

            if (isset($wr[$this->end])) {
                $this->addNotFind($r, $n, $s, $i, $auto_info);
                $this->addResult($r, $s, $i, $wr[$this->x]);
                $wr = $this->dict;
                $s  = '';
                $n  = '';
            }

        }
        if (isset($wr[$this->end])) {
            $this->addNotFind($r, $n, $s, $i, $auto_info);
            $this->addResult($r, $s, $i, $wr[$this->x]);
        } else {
            $this->addNotFind($r, $n, '', $i, $auto_info);
        }
        return $r;
    }

    /**
     * @param $r
     * @param $k
     * @param $i
     * @param $x
     * @param int $find
     */
    private function addResult(&$r, $k, $i, $x, $find = 1)
    {
        $r[] = [$k, $i, $x, $find];
    }

}