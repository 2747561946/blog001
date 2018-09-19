<?php
namespace controllers;

class IndexController
{
    public function index()
    {
        // 取最新的日志
        $blog = new \models\Blog;
        $blogs = $blog->getNew();

        // 取取活跃用户
        $user = new \models\User;
        $users = $user->getActiveUsers();

        // 显示页面
        view('index.index', [
            'blogs' => $blogs,
            'users' => $users,
        ]);
    }
}