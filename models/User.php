<?php
namespace models;

use PDO;

class User extends Base
{
    public function getActiveUsers()
    {
        $redis = \libs\Redis::getInstance();
        $data = $redis->get('active_users');
        // 转回数组（第二个参数 true:数组    false：对象）
        return json_decode($data, true);
    }

    // 计算活跃用户
    public function computeActiveUsers()
    {
        // 取日志的分值
        $stmt = self::$pdo->query('SELECT user_id,COUNT(*)*5 fz
                                    FROM blogs
                                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)
                                        GROUP BY user_id');
        $data1 = $stmt->fetchAll( PDO::FETCH_ASSOC );

        /*
            $data1 = [
                [
                    'user_id' => 3,
                    'fz' => 100,
                ],
                [
                    'user_id' => 2,
                    'fz' => 43,
                ],
            ];
        */

        // 取评论的分值
        $stmt = self::$pdo->query('SELECT user_id,COUNT(*)*3 fz
                    FROM comments
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)
                        GROUP BY user_id');
        $data2 = $stmt->fetchAll( PDO::FETCH_ASSOC );

        // 取点赞的分值
        $stmt = self::$pdo->query('SELECT user_id,COUNT(*) fz
                    FROM blog_agrees
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)
                        GROUP BY user_id');
        $data3 =$stmt->fetchAll( PDO::FETCH_ASSOC );

        // 合并数组
        $arr = [];       // 空数组

        // 合并第1个数组到空数组中
        foreach($data1 as $v)
        {
            $arr[$v['user_id']] = $v['fz'];
        }

        // 合并第2个数组到数组中
        foreach($data2 as $v)
        {
            if( isset($arr[$v['user_id']]) )
                $arr[$v['user_id']] += $v['fz'];
            else
                $arr[$v['user_id']] = $v['fz'];
        }

        // 合并第3个数组到数组中
        foreach($data3 as $v)
        {
            if( isset($arr[$v['user_id']]) )
                $arr[$v['user_id']] += $v['fz'];
            else
                $arr[$v['user_id']] = $v['fz'];
        }

        // 倒序排序
        arsort($arr);

        // 取前20并保存键（第四个参数保留键）
        $data = array_slice($arr, 0, 20, TRUE);

        // 取出前20用户的ID
        // 从数组中取出所有的键
        $userIds = array_keys($data);
        // 数组转字符中 = [1,2,3,4,5,6,8];   =>  '1,2,3,4,5,6,7'
        $userIds = implode(',', $userIds);

        // 取出用户的 头像 和 email
        $sql = "SELECT id,email,avatar FROM users WHERE id IN($userIds)";

        $stmt = self::$pdo->query($sql);
        $data = $stmt->fetchAll( PDO::FETCH_ASSOC );


        // 把计算的结果保存到 Redis，因为 Redis 中只能保存字符串，所以我们需要把数组转成JSON字符串
        $redis = \libs\Redis::getInstance();
        $redis->set('active_users', json_encode($data));

    }

    public function setAvatar($path)
    {
        $stmt = self::$pdo->prepare('UPDATE users SET avatar=? WHERE id=?');
        $stmt->execute([
            $path,
            $_SESSION['id']
        ]);
    }

    public function add($email,$password)
    {
        $stmt = self::$pdo->prepare("INSERT INTO users (email,password) VALUES(?,?)");
        return $stmt->execute([
                                $email,
                                $password,
                            ]);
    }

    public function login($email, $password)
    {
        // 根据 email 和 password 查询数据库
        $stmt = self::$pdo->prepare("SELECT * FROM users WHERE email=? AND password=?");
        // 执行 SQL
        $stmt->execute([
            $email,
            $password
        ]);
        // 取出数据
        $user = $stmt->fetch();
        // 是否有这个账号
        if( $user )
        {
            // 登录成功，把用户信息保存到 SESSION
            $_SESSION['id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['money'] = $user['money'];
            $_SESSION['avatar'] = $user['avatar'];
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }

    // 为用户增加金额
    public function addMoney($money, $userId)
    {
        $stmt = self::$pdo->prepare("UPDATE users SET money=money+? WHERE id=?");
        return $stmt->execute([
            $money,
            $userId
        ]);

        

    }

    // 获取余额
    public function getMoney()
    {
        $id = $_SESSION['id'];
        // 查询数据库        
        $stmt = self::$pdo->prepare('SELECT money FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $money = $stmt->fetch( PDO::FETCH_COLUMN );
        // 更新到 SESSION 中
        $_SESSION['money'] = $money;
        return $money;
    }

    // 测试事务
    public function trans()
    {
        // 要求：所有的SQL语句必须都成功，或者都失败

        // 事务：让多条 SQL 语句都成功或者都失败
        // 如何使用事务

        // 开启事务
        self::$pdo->exec('start transaction');

        // 执行多个 SQL 
        $ret1 = self::$pdo->exec("update users set email='abc@126.com' where id=2");  // 正确的
        $ret2 = self::$pdo->exec("update users set email='bcd@126.com',money='123.32' where id=3");  // 正确


        // 只有都成功时才提交事务，否则回滚事务
        if($ret1 !== FALSE && $ret2 !== FALSE)
            self::$pdo->exec('commit');    // 提交事务
        else
            self::$pdo->exec('rollback');  // 回滚事务
    }


    public function getAll()
    {
        $stmt = self::$pdo->query('SELECT * FROM users');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
