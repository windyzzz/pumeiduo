<?php

namespace app\home\command\supplier;


use app\common\model\Region2 as Region2Model;
use app\common\model\SpecialRegion as SpecialRegionModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class Region extends Command
{
    protected function configure()
    {
        $this->setName('collect_supplier_region')
            ->setDescription('匹配保存供应链地区数据');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set('memory_limit', '512M');
        $start = microtime(true);
        $output->writeln('开始处理：' . date('Y-m-d H:i:s'));
        // 更新原本地数据
        $this->updateRegion1($output);
        // 比较地区数据，进行整合
        $this->differRegion($output);
        // 更新本地未更新成功的地区
        $this->updateRegion2($output);
        $output->writeln('程序结束：' . date('Y-m-d H:i:s'));
        $end = microtime(true);
        $output->writeln('所用时间：' . bcsub($end, $start, 5));
    }

    /**
     * 更新原本地数据
     * @param Output $output
     */
    public function updateRegion1(Output $output)
    {
        $region = M('region2')->where(['parent_id' => ['NEQ', 0]])->field('id, parent_id')->select();
        $delRegionIds = [];
        foreach ($region as $v) {
            $parentId = M('region2')->where(['id' => $v['parent_id']])->value('id');
            if (!$parentId) {
                $delRegionIds[] = $v['id'];
            }
        }
        M('region2')->where(['id' => ['IN', $delRegionIds]])->delete();
        $output->writeln('更新原本地数据 成功');
    }

    /**
     * 比较地区数据，进行整合
     * @param Output $output
     * @throws \Exception
     */
    public function differRegion(Output $output)
    {
        // 本地一级地区
        $localRegion1 = M('region2')->where(['level' => 0])->field('id, name')->select();
        // 供应链一级地区
        $mlRegion1 = M('ml_region')->where(['level' => 1, 'is' => 1])->field('id, name')->select();
        $mlRegionIdsL1 = [];
        $mlRegionIdsL2 = [];
        $mlRegionIdsL3 = [];
        $insertRegionL4 = [];
        foreach ($localRegion1 as $region1_1) {
            foreach ($mlRegion1 as $region2_1) {
                if (stristr(mb_substr(trim_all($region1_1['name']), 0, 2), mb_substr(trim_all($region2_1['name']), 0, 2))) {
                    // 两边都有的
                    $mlRegionIdsL1[] = $region2_1['id'];
                    M('region2')->where(['id' => $region1_1['id']])->update(['ml_region_id' => $region2_1['id']]);
                    // 本地二级地区
                    $localRegion2 = M('region2')->where(['parent_id' => $region1_1['id']])->field('id, name')->select();
                    // 供应链二级地区
                    $mlRegion2 = M('ml_region')->where(['parent_id' => $region2_1['id']])->field('id, name, is')->select();
                    foreach ($localRegion2 as $region1_2) {
                        foreach ($mlRegion2 as $region2_2) {
                            if (stristr(mb_substr(trim_all($region1_2['name']), 0, 2), mb_substr(trim_all($region2_2['name']), 0, 2))) {
                                // 两边都有的
                                $mlRegionIdsL2[] = $region2_2['id'];
                                M('region2')->where(['id' => $region1_2['id']])->update(['ml_region_id' => $region2_2['id']]);
                                // 本地三级地区
                                $localRegion3 = M('region2')->where(['parent_id' => $region1_2['id']])->field('id, name')->select();
                                // 供应链三级地区
                                $mlRegion3 = M('ml_region')->where(['parent_id' => $region2_2['id']])->field('id, name, is')->select();
                                foreach ($localRegion3 as $region1_3) {
                                    foreach ($mlRegion3 as $region2_3) {
                                        if (stristr(mb_substr(trim_all($region1_3['name']), 0, 2), mb_substr(trim_all($region2_3['name']), 0, 2))) {
                                            $mlRegionIdsL3[] = $region2_3['id'];
                                            // 两边都有的
                                            M('region2')->where(['id' => $region1_3['id']])->update(['ml_region_id' => $region2_3['id']]);
                                            // 供应链第四级地区
                                            $mlRegion4 = M('ml_region')->where(['parent_id' => $region2_3['id']])->field('id, name, is')->select();
                                            foreach ($mlRegion4 as $region2_4) {
                                                $insertRegionL4[] = [
                                                    'name' => $region2_4['name'],
                                                    'parent_id' => $region1_3['id'],
                                                    'level' => 3,
                                                    'zipcode' => 0,
                                                    'ml_region_id' => $region2_4['id']
                                                ];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        if (!empty($insertRegionL4)) {
            $regionModel = new Region2Model();
            $regionModel->saveAll($insertRegionL4);
        }
        // 供应链存在但本地不存在的第一级地区
        $mlLevel1 = M('ml_region')->where(['level' => 1, 'is' => 1, 'id' => ['NOT IN', $mlRegionIdsL1]])->select();
        $mlLevel1Ids = array_keys(array_combine(array_column($mlLevel1, 'id'), array_values($mlLevel1)));
        foreach ($mlLevel1 as $region1) {
            $insertId1 = M('region2')->insertGetId([
                'name' => $region1['name'],
                'parent_id' => 0,
                'level' => 0,
                'zipcode' => 0,
                'ml_region_id' => $region1['id']
            ]);
            // 第二级地区
            $mlLevel2 = M('ml_region')->where(['parent_id' => $region1['id']])->select();
            foreach ($mlLevel2 as $region2) {
                $insertId2 = M('region2')->insertGetId([
                    'name' => $region2['name'],
                    'parent_id' => $insertId1,
                    'level' => 1,
                    'zipcode' => 0,
                    'ml_region_id' => $region2['id']
                ]);
                // 第三级地区
                $mlLevel3 = M('ml_region')->where(['parent_id' => $region2['id']])->select();
                foreach ($mlLevel3 as $region3) {
                    $insertId3 = M('region2')->insertGetId([
                        'name' => $region3['name'],
                        'parent_id' => $insertId2,
                        'level' => 2,
                        'zipcode' => 0,
                        'ml_region_id' => $region3['id']
                    ]);
                    // 第四级地区
                    $mlLevel4 = M('ml_region')->where(['parent_id' => $region3['id']])->select();
                    foreach ($mlLevel4 as $region4) {
                        M('region2')->insertGetId([
                            'name' => $region4['name'],
                            'parent_id' => $insertId3,
                            'level' => 3,
                            'zipcode' => 0,
                            'ml_region_id' => $region4['id']
                        ]);
                    }
                }
            }
        }
        // 供应链存在但本地不存在的第二级地区
        $mlLevel2 = M('ml_region')->where(['level' => 2, 'is' => 1, 'id' => ['NOT IN', $mlRegionIdsL2]])->select();
        $mlLevel2Ids = array_keys(array_combine(array_column($mlLevel2, 'id'), array_values($mlLevel2)));
        foreach ($mlLevel2 as $region2) {
            if (in_array($region2['parent_id'], $mlLevel1Ids)) {
                continue;
            }
            $parentId = M('region2')->where(['ml_region_id' => $region2['parent_id']])->value('id');
            if (!$parentId) {
                continue;
            }
            $insertId2 = M('region2')->insertGetId([
                'name' => $region2['name'],
                'parent_id' => $parentId,
                'level' => 1,
                'zipcode' => 0,
                'ml_region_id' => $region2['id']
            ]);
            // 第三级地区
            $mlLevel3 = M('ml_region')->where(['parent_id' => $region2['id']])->select();
            foreach ($mlLevel3 as $region3) {
                $insertId3 = M('region2')->insertGetId([
                    'name' => $region3['name'],
                    'parent_id' => $insertId2,
                    'level' => 2,
                    'zipcode' => 0,
                    'ml_region_id' => $region3['id']
                ]);
                // 第四级地区
                $mlLevel4 = M('ml_region')->where(['parent_id' => $region3['id']])->select();
                foreach ($mlLevel4 as $region4) {
                    M('region2')->insertGetId([
                        'name' => $region4['name'],
                        'parent_id' => $insertId3,
                        'level' => 3,
                        'zipcode' => 0,
                        'ml_region_id' => $region4['id']
                    ]);
                }
            }
        }
        // 供应链存在但本地不存在的第三级地区
        $mlLevel3 = M('ml_region')->where(['level' => 3, 'is' => 1, 'id' => ['NOT IN', $mlRegionIdsL3]])->select();
        foreach ($mlLevel3 as $region3) {
            if (in_array($region3['parent_id'], $mlLevel2Ids)) {
                continue;
            }
            $parentId = M('region2')->where(['ml_region_id' => $region3['parent_id']])->value('id');
            if (!$parentId) {
                continue;
            }
            $insertId3 = M('region2')->insertGetId([
                'name' => $region3['name'],
                'parent_id' => $parentId,
                'level' => 2,
                'zipcode' => 0,
                'ml_region_id' => $region3['id']
            ]);
            // 第四级地区
            $mlLevel4 = M('ml_region')->where(['parent_id' => $region3['id']])->select();
            foreach ($mlLevel4 as $region4) {
                M('region2')->insertGetId([
                    'name' => $region4['name'],
                    'parent_id' => $insertId3,
                    'level' => 3,
                    'zipcode' => 0,
                    'ml_region_id' => $region4['id']
                ]);
            }
        }
        $output->writeln('比较地区数据，进行整合 成功');
    }

    /**
     * 更新本地未更新成功的地区
     * @param Output $output
     * @throws \Exception
     */
    public function updateRegion2(Output $output)
    {
        $localRegion = M('region2')->where(['ml_region_id' => 0])->select();
        $specialRegionData = [];
        foreach ($localRegion as $region) {
            $brotherRegion = M('region2')->where(['parent_id' => $region['parent_id'], 'ml_region_id' => ['NEQ', 0]])->value('ml_region_id');
            if ($brotherRegion) {
                M('region2')->where(['id' => $region['id']])->update(['ml_region_id' => $brotherRegion]);
            }
            unset($region['id']);
            unset($region['ml_region_id']);
            $specialRegionData[] = $region;
        }
        if (!empty($specialRegionData)) {
            $localRegionModel = new SpecialRegionModel();
            $localRegionModel->saveAll($specialRegionData);
        }
        $output->writeln('更新本地未更新成功的地区 成功');
    }
}