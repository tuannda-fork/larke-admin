<?php

namespace Larke\Admin\Event;

use Larke\Admin\Model\Admin as AdminModel;

/*
 * 登陆之后
 *
 * @create 2020-11-2
 * @author deatil
 */
class PassportLoginAfter
{
    /**
     * Request 实例
     * @var \Larke\Admin\Model\Admin
     */
    public $admin;
    
    /**
     * 构造方法
     * @access public
     * @param  \Larke\Admin\Model\Admin  $admin
     */
    public function __construct(AdminModel $admin)
    {
        $this->admin = $admin;
    }
    
}
