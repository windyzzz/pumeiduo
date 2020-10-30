<?php

namespace app\home\command\supplier;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Exception;

class Goods extends Command
{
    protected function configure()
    {
        $this->setName('update_supplier_goods')
            ->setDescription('供应链商品更新价格属性');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set('memory_limit', '512M');
        $start = microtime(true);
        $output->writeln('开始处理：' . date('Y-m-d H:i:s'));
        // 更新商品信息（excel文件导入）
        $this->updateGoods($output);
        $output->writeln('程序结束：' . date('Y-m-d H:i:s'));
        $end = microtime(true);
        $output->writeln('所用时间：' . bcsub($end, $start, 5));
    }

    /**
     * 更新商品信息（excel文件导入）
     * @param Output $output
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     */
    public function updateGoods(Output $output)
    {
        $file = \think\Env::get('COMMAND.PUBLIC') . "download_excel/商品价格导入_20201029.xlsx";
        include_once "plugins/PHPExcel.php";
        $objRead = new \PHPExcel_Reader_Excel2007();   //建立reader对象

        $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');
        $obj = $objRead->load($file);  //建立excel对象
        $currSheet = $obj->getSheet(0);   //获取指定的sheet表
        $columnH = $currSheet->getHighestColumn();   //取得最大的列号
        $columnCnt = array_search($columnH, $cellName);
        $rowCnt = $currSheet->getHighestRow();   //获取总行数

        $data = array();
        for ($_row = 1; $_row <= $rowCnt; $_row++) {  //读取内容
            for ($_column = 0; $_column <= $columnCnt; $_column++) {
                $cellId = $cellName[$_column] . $_row;
                $cellValue = $currSheet->getCell($cellId)->getValue();
                //$cellValue = $currSheet->getCell($cellId)->getCalculatedValue();  #获取公式计算的值
                if ($cellValue instanceof \PHPExcel_RichText) {   //富文本转换字符串
                    $cellValue = $cellValue->__toString();
                }
                $data[$_row][$cellName[$_column]] = $cellValue;
            }
        }
        try {
            Db::startTrans();
            foreach ($data as $k => $v) {
                if ($k == 1) {
                    continue;
                }
                // 获取商品编号
                $res = $this->sendSyncData(3, 4, ['supplier_goods_sn' => $v['A']]);
                $res = json_decode($res, true);
                if ($res['status'] == 0) {
                    $output->writeln($v['B'] . '：' . $res['msg']);
                    continue;
                }
                $goodsSn = $res['result']['goods']['goods_sn'];
                // 获取第三级分类ID
                $cateId = M('goods_category')->where(['name' => $v['E'], 'level' => 3])->value('id');
                // 更新商品信息
                $upData = [
                    'cat_id' => $cateId,
                    'commission' => $v['F'],
                    'exchange_integral' => round($v['G'], 2),
                    'shop_price' => round($v['H'], 2),
                    'stax_price' => round($v['I'], 2),
                    'ctax_price' => round($v['J'], 2),
                    'give_integral' => round($v['K'], 2),
                    'integral_pv' => round($v['L'], 2),
                    'is_free_shipping' => 0,
                    'template_id' => 2,
                    'is_area_show' => 1,
                    'is_on_sale2' => 1
                ];
                $res = M('goods')->where(['goods_sn' => $goodsSn])->update($upData);
                if (!$res) {
                    $output->writeln($v['B'] . '：更新失败');
                }
            }
            Db::commit();
            $output->writeln('处理成功');
        } catch (Exception $e) {
            Db::rollback();
            $output->writeln('处理失败，原因：' . $e->getMessage());
        }
    }

    /**
     * 发送数据同步
     * @param $system
     * @param $type
     * @param $syncData
     * @return mixed
     */
    function sendSyncData($system, $type, $syncData)
    {
        $url = $this->get_url($system);
        $res = httpRequest($url, 'POST', ['type' => $type, 'data' => json_encode($syncData)]);
        return $res;
    }

    /**
     * 同步方法地址
     * @param $to_system
     * @return mixed
     */
    function get_url($to_system)
    {
        // 测试连接
        $test_ip = '61.238.101.139';
        $test_url_arr = array(
            1 => '',
            2 => '',
            3 => 'http://pmderp.meetlan.com/index.php/supplier.Sync/getSyncData'
        );
        // 正式链接
        $online_ip = '61.238.101.138';
        $online_url_arr = array(
            1 => '',
            2 => '',
            3 => 'http://192.168.194.7/index.php/supplier.Sync/getSyncData'
        );
        // 本地链接
        $local_url_arr = array(
            3 => 'http://pumeiduo.erp/index.php/supplier.Sync/getSyncData'
        );
        switch (\think\Env::get('COMMAND.IP')) {
            case $test_ip:
                return $test_url_arr[$to_system];
            case $online_ip:
                return $online_url_arr[$to_system];
            default:
                return $local_url_arr[$to_system];
        }
    }
}