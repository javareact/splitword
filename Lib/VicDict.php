<?php

/**
 * Created by PhpStorm.
 * User: Fuzzyy
 * Date: 2017/12/21
 * Time: 下午8:16
 */

namespace JavaReact\SplitWord;

/**
 * Class VicDict
 * @package JavaReact\SplitWord
 */
class VicDict
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
    private $word = [];
    /**
     * 词典地址
     * @var string
     */
    private $code = 'utf-8';

    /**
     * @var array
     */
    private $end = ['\\' => 1];

    /**
     * @var array
     */
    private $default_end = ['\\' => 1];

    /**
     * @var string
     */
    private $end_key = '\\';

    /**
     * @var string
     */
    private $type = self::TYPE_JSON;

    /**
     * VicDict constructor.
     * @param string $type
     * @throws \Exception
     */
    public function __construct($type = self::TYPE_JSON)
    {
        define('_VIC_WORD_DICT_PATH_', __DIR__ . '/../Data/dict.' . $type);
        $this->type = $type;
        if (!file_exists(_VIC_WORD_DICT_PATH_)) {
            throw new \Exception('分词文件不存在');
        }
        switch ($type) {
            case self::TYPE_IGB:
                if (!function_exists('igbinary_unserialize')) {
                    throw new \Exception('需要安装igb扩展');
                }
                $this->word = igbinary_unserialize(file_get_contents(_VIC_WORD_DICT_PATH_));
                break;
            case self::TYPE_JSON:
            default:
                $this->word = json_decode(file_get_contents(_VIC_WORD_DICT_PATH_), true);
                break;
        }
    }

    /**
     * @param string $word
     * @param null|string $x 词性
     * @return bool
     */
    public function add($word, $x = null)
    {
        $this->end = ['\\x' => $x] + $this->default_end;
        $word      = $this->filter($word);
        if ($word) {
            return $this->merge($word);
        }
        return false;
    }

    /**
     * @param $word
     * @return bool
     */
    private function merge($word)
    {
        $ar = $this->toArr($word);
        $br = $ar;
        $wr = &$this->word;
        foreach ($ar as $i => $v) {
            array_shift($br);
            if (!isset($wr[$v])) {
                $wr[$v] = $this->dict($br, $this->end);
                return true;
            } else {
                $wr = &$wr[$v];
            }
        }
        if (!isset($wr[$this->end_key])) {
            foreach ($this->end as $k => $v) {
                $wr[$k] = $v;
                $wr[$k] = $v;
            }
        }
        return true;
    }

    /**
     * @return bool|int
     */
    public function save()
    {
        if ($this->type == self::TYPE_IGB) {
            $str = igbinary_serialize($this->word);
        } else {
            $str = json_encode($this->word);
        }
        return file_put_contents(_VIC_WORD_DICT_PATH_, $str);
    }

    /**
     * @param $word
     * @return mixed
     */
    private function filter($word)
    {
        return str_replace(["\n", "\t"], '', trim($word));
    }

    /**
     * @param $arr
     * @param $v
     * @param int $i
     * @return array
     */
    private function dict($arr, $v, $i = 0)
    {
        if (isset($arr[$i])) {
            return [$arr[$i] => $this->dict($arr, $v, $i + 1)];
        } else {
            return $v;
        }
    }

    /**
     * @param $str
     * @return array
     */
    private function toArr($str)
    {
        $l = mb_strlen($str, $this->code);
        $r = [];
        for ($i = 0; $i < $l; $i++) {
            $r[] = mb_substr($str, $i, 1, $this->code);
        }
        return $r;
    }

}