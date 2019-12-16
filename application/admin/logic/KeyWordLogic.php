<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\logic;

use app\common\model\KeyWord;
use think\Db;
use think\Exception;

class KeyWordLogic
{
    private $model;
    public $error = '';

    public function __construct()
    {
        $this->model = new KeyWord();
    }

    public function getList()
    {
        return $this->model->order('sort_order')->select();
    }

    public function getCount()
    {
        return $this->model->count();
    }

    public function getById($id)
    {
        return $this->model->find($id);
    }

    public function store($data)
    {
        return $this->model->save($data);
    }

    public function delete($id)
    {
        Db::startTrans();
        try {
            $this->model->where('id', $id)->delete();
        } catch (Exception $e) {
            Db::rollback();

            return false;
        }
        Db::commit();

        return true;
    }

    public function update($data)
    {
        Db::startTrans();
        try {
            $this->model->update($data);
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            Db::rollback();

            return false;
        }
        Db::commit();

        return true;
    }
}
