<?php

namespace Te\Protocols;

class Stream implements Protocol
{

    /**
     * 用于检测数据是否完整
     *
     * @param $data
     *
     * @return bool
     */
    public function len($data)
    {
        $dataStrLen = strlen($data);
        if ($dataStrLen < 4) {
            return false;
        }

        $tmp = unpack('NtotalLen', $data);

        if ($dataStrLen < $tmp['totalLen']) {
            return false;
        }

        return true;
    }

    /**
     * @param   string  $data
     *
     * @return mixed
     */
    public function encode($data = '')
    {
        //+6是预定义的，4个字节存长度
        $totalLen = strlen($data) + 6;
        $bin      = pack('Nn', $totalLen, '1') . $data;

        return [$totalLen, $bin];
    }

    /**
     * @param   string  $data
     *
     * @return false|string
     */
    public function decode($data = '')
    {
        $cmd = substr($data, 4, 2);
        $msg = substr($data, 6);

        return $msg;
    }

    /**
     * 返回一条数据的总长度
     *
     * @param   string  $data
     *
     * @return mixed
     */
    public function msgLen($data = '')
    {
        $tmp = unpack('Nlength', $data);

        return $tmp['length'];
    }
}