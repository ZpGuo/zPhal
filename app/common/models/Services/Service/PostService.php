<?php

namespace ZPhal\Models\Services\Service;

use ZPhal\Models\Services\AbstractService;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use ZPhal\Modules\Admin\Library\Paginator\Pager;

/**
 * Posts服务类
 * Class PostService
 * @package ZPhal\Models\Services\Service
 */
class PostService extends AbstractService
{
    /**
     * @var mixed|\Phalcon\DiInterface
     */
    private static $modelsManager;

    /**
     * PostService constructor.
     * @param null $di
     */
    public function __construct($di = null)
    {
        parent::__construct($di);
        self::$modelsManager = $this->di->get('modelsManager') ?: container('modelsManager');
    }

    /**
     * 根据post类型获取post统计数量
     * @param $type
     * @return mixed
     */
    public function staticPost($postType)
    {
        $count = self::$modelsManager->executeQuery(
            "SELECT sum(case post_status when 'publish' then 1 else 0 end) AS publish_num,
            sum(case post_status when 'draft' then 1 else 0 end) AS draft_num,
            sum(case post_status when 'trash' then 1 else 0 end) AS trash_num
            FROM ZPhal\Models\Posts 
            WHERE post_type = :posttype:",
            [
                'posttype' => $postType
            ]
        )->toArray();

        return $count[0];
    }

    /**
     * 根据post类型获取所有结果的时间区间(格式:年-月 '%Y-%m')
     * @param $type
     * @return mixed
     */
    public function getDateSection($postType)
    {
        return self::$modelsManager->executeQuery(
            "SELECT DATE_FORMAT(post_date,'%Y-%m') AS year_month
            FROM ZPhal\Models\Posts
            WHERE post_type = :posttype: 
            GROUP BY year_month 
            ORDER BY year_month DESC",
            [
                'posttype' => $postType
            ]
        )->toArray();
    }

    /**
     * 获取post列表
     * TODO 根据权限获取
     * @param $type
     * @param $show
     * @param $currentPage
     * @param $condition
     * @return Pager
     */
    public function getPostListByType($type, $show, $currentPage, $condition)
    {
        // sql builder
        $builder = self::$modelsManager->createBuilder()
            ->columns("p.ID, p.post_title, p.post_status, p.post_author, p.post_date, p.comment_count, p.view_count, u.display_name,
                       GROUP_CONCAT( t.name, tt.taxonomy ) terms")
            ->from(['p' => 'ZPhal\Models\Posts'])
            ->leftJoin('ZPhal\Models\Users', 'u.ID = p.post_author', "u")
            ->leftJoin('ZPhal\Models\TermRelationships', 'ts.object_id = p.ID', 'ts')
            ->leftJoin('ZPhal\Models\TermTaxonomy', 'tt.term_taxonomy_id = ts.term_taxonomy_id', 'tt')
            ->leftJoin('ZPhal\Models\Terms', 't.term_id = tt.term_id', 't')
            ->where("p.post_type = :type:", ["type" => $type]);

        if ($condition['categorySelected']){
            $builder->andWhere("tt.term_taxonomy_id = :categorySelected:", [ "categorySelected" => $condition['categorySelected']]);
        }

        if ($condition['dateSelected']){
            $builder->andWhere("DATE_FORMAT(p.post_date,'%Y-%m') = :dateSelected:", ["dateSelected" => $condition['dateSelected']]);
        }

        switch ($show){
            case 'all':
                $builder->andWhere("p.post_status <> 'trash'");
                break;
            case 'publish':
                $builder->andWhere("p.post_status = 'publish'");
                break;
            case 'draft':
                $builder->andWhere("p.post_status = 'draft'");
                break;
            case 'trash':
                $builder->andWhere("p.post_status = 'trash'");
                break;
            default:
                break;
        }

        if (!empty($condition['search'])){
            $builder->andWhere("p.post_title LIKE :title:", ["title" => "%" . $condition['search'] . "%"]);
        }
        $builder->groupBy("p.ID");
        $builder->orderBy('p.ID desc');

        // 分页额外url传参
        $urlMask = '?page={%page_number}';
        foreach ($condition as $key => $value){
            $urlMask .= '&'.$key.'='.$value;
        }

        // 分页查询
        return $pager = new Pager(
            new PaginatorQueryBuilder(
                [
                    'builder' => $builder,
                    'limit'   => 20,
                    'page'    => $currentPage,
                ]
            ),
            [
                'layoutClass' => 'ZPhal\Modules\Admin\Library\Paginator\Pager\Layout\Bootstrap', // 样式类
                'rangeLength' => 5, // 分页长度
                'urlMask'     => $urlMask, // 额外url传参
            ]
        );
    }

    /**
     * 获取post信息
     * @param $id
     * @param string $type
     * @param string $version
     * @return mixed
     */
    public function getPostInfo($id, $type='post')
    {
        $info = self::$modelsManager->executeQuery(
            "SELECT ID, post_date, post_content, post_title, post_status, comment_status, post_name, post_modified, post_parent, guid, cover_picture
            FROM ZPhal\Models\Posts
            WHERE ID = :postId: AND post_type = :postType: ",
            [
                'postId'    => $id,
                'postType'  => $type
            ]
        )->toArray();

        return $info[0];
    }
}