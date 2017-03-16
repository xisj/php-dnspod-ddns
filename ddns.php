<?php

/**
 * 一个单文件的dnspod ddns 动态域名解析的实现
 * @auther terry<jstel###126.com>
 * @license 你随便用， 如果有转发， 留下我的名字
 * @link URL description
 */
/* * **************程序配置项目开始****************** */

/**
 * 下面的两个变量生成说明见这里：
 * https://support.dnspod.cn/Kb/showarticle/tsid/227/
 */
$api_id = 123456;
$api_token = '123456789098765432qwertyuioiuytrewq';

/**
 * domain:一级域名，sub_domain: 二级域名
 * 如果一次更新多个域名， 就复制这个数组多次
 */
$data[] = array('domain' => 'zhuikan.com', 'sub_domain' => 'publish');
//$data[] = array('domain'=>'zhuikan.com',$sub_domain => 'test');
//$data[] = array('domain'=>'zhuikan.com',$sub_domain => 'dev');
//为了符合dnspod的要求， 在本机ip不变更的时候， 不会主动去刷新api
//每次更新的ip和域名的数据， 都存在这个文件里面
$cache_file = '/tmp/php-dnspod-ddns.tmp';

/* * **************程序配置项目结束****************** */
/* * ********************************************** */



/* * ********************************************** */
/* * **************下面开始不需要修改*************** */

$ddns = new ddns($cache_file);
$ddns->set_login_token($api_id, $api_token);



$list = $ddns->get_domain_list();

foreach ($data as $k => $v) {
    echo "\nSETTING: {$v['sub_domain']}.{$v['domain']}.....";
    $ret = $ddns->domain_modify($v['domain'], $v['sub_domain']);
    if ($ret) {
        echo " SUCCESS!\n";
    } else {

        echo " FAIL!\n";
    }
}

class ddns {

    private $login_token = '';
    public $cache_file = '/tmp/php-dnspod-ddns.tmp';
    public $ip = '';
    public $need_refresh = TRUE;
    public $domain_list = array();
    public $format = 'json';

    public function __construct($cache_file) {
        if (!is_file($cache_file)) {
            touch($cache_file);
        }
        $this->cache_file = $cache_file;
    }

    public function set_login_token($api_id, $api_token) {
        $this->login_token = $api_id . ',' . $api_token;
    }

    public function get_real_ip() {
        if (empty($this->ip)) {
            $this->ip = file_get_contents('http://ip.zhuikan.com/?p=php-dnspod-ddns&v=1.0');
        }
        return $this->ip;
    }

    public function check_if_need_refresh($domain, $sub_domain) {

        $cache = json_decode(file_get_contents($this->cache_file), TRUE);
        $ip = $this->get_real_ip();
        if (@$cache[$sub_domain . '.' . $domain]['ip'] == $ip) {
            $this->need_refresh = FALSE;
        }

        return $this->need_refresh;
    }

    public function get_domain_list($keyword = '') {
        $data['login_token'] = $this->login_token;
        $data['format'] = $this->format;
        return $this->domain_list = $this->post_data('Domain.List', $data);
    }

    public function get_domain_record($domain) {
        $data['domain'] = $domain;
        $data['login_token'] = $this->login_token;
        $data['format'] = $this->format;
        return $this->domain_list = $this->post_data('Record.List', $data);
    }

    public function get_domain_id($domain) {
        if (empty($this->domain_list)) {
            $this->domain_list = $this->get_domain_list();
        }
        if ($this->domain_list['status']['code'] !== "1") {
            $this->error($this->domain_list['status']['code'], $this->domain_list['status']['message']);
        }
        $domain_id = 0;

        foreach ($this->domain_list['domains'] as $k => $v) {
            if ($v['name'] == $domain) {
                $domain_id = $v['id'];
            }
        }
        if ($domain_id < 1) {
            $this->error('E001', 'domain:' . $domain . ' not exists');
        }
        return $domain_id;
    }

    public function domain_modify($domain, $sub_domain) {
        if (!$this->check_if_need_refresh($domain, $sub_domain)) {
            echo "\n$sub_domain.$domain :IP HAS NOT CHANGED\n";
            return FALSE;
        }
        $domain_record = $this->get_domain_record($domain);
        if ($domain_record['status']['code'] !== "1") {
            $this->error($domain_record['status']['code'], $domain_record['status']['message']);
        }

        $record_data = array();
        foreach ($domain_record['records'] as $k => $v) {
            if ($v['name'] == $sub_domain) {
                $record_data = $v;
            }
        }
        if (empty($record_data)) {
            $this->error('E002', 'sub_domain:' . $sub_domain . '.' . $domain . ' not exists');
        }

        $data['record_id'] = $record_data['id'];
        $data['domain'] = $domain;
        $data['sub_domain'] = $sub_domain;
        $data['record_line_id'] = $record_data['line_id'];
        $data['value'] = $this->ip;
        $data['record_type'] = $record_data['type'];
        $data['mx'] = $record_data['mx'];

        $ret = $this->post_data('Record.Modify', $data);

        if ($ret['status']['code'] === "1") {
            $this->put_cache($this->ip, $domain, $sub_domain);
        } else {
            $this->error($ret['status']['code'], $ret['status']['message']);
        }
        return TRUE;
    }

    private function put_cache($ip, $domain, $sub_domain) {
        $cache = json_decode(file_get_contents($this->cache_file), 1);
        $cache[$sub_domain . '.' . $domain] = array('ip' => $this->ip, 'domain' => $domain, 'sub_domain' => $sub_domain, 'create_time' => date('Y-m-d H:i:s'));
        file_put_contents($this->cache_file, json_encode($cache));
    }

    private function post_data($api, $data, $cookie = '') {

        $url = 'https://dnsapi.cn/' . $api;
        if ($url == '' || !is_array($data)) {
            die('danger内部错误：参数错误');
        }

        $data['login_token'] = $this->login_token;
        $data['format'] = $this->format;

        $ch = @curl_init();
        if (!$ch) {
            die('danger 内部错误：服务器不支持CURL');
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSLVERSION, 1);
//    curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERAGENT, 'DNSPod API PHP Web Client/1.0.0 (i@likexian.com)');
        $result = curl_exec($ch);
        curl_close($ch);
        $result = explode("\r\n\r\n", $result);
        return json_decode($result[1], 1);
    }

    public function error($code, $message) {
        die("code:$code message:$message\n");
    }

}
