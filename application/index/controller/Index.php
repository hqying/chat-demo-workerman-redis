<?php
namespace app\index\controller;
use think\Controller;
use think\Db;

class Index extends Controller
{
    public function index()
    {
        $sql = "select * from student";
        $main_data = Db::query($sql);
        $this->assign('data',  json_encode($main_data));
        $this->assign('dataphp', $main_data);

        $sql = "select * from student group by country";
        $this->assign('countries', Db::query($sql));
        return $this->fetch();
    }

    public function insertIntoStudent() {
        $sql = "insert into student (name, city, country) values (?, ?, ?)";
        Db::execute($sql, [$_GET['name'], $_GET['city'], $_GET['country']]);
    }

    public function selectByCountry($country) {
        $main_data = Db::table('student')->where('country', $country)->select('name');
//        $main_data = Db::table('student')->where('country', $country)->column('name,city');
        return json($main_data);
    }
}
