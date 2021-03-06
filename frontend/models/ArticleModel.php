<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/8/10
 * Time: 21:30
 */

namespace frontend\models;


use backend\component\Message;
use yii\db\ActiveRecord;

class ArticleModel extends  ActiveRecord
{
    const SCENARIO_ADD = 'add';//添加场景
    const SCENARIO_SAVE = 'save';//修改场景
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%article}}';

    }
    public function rules()
    {
        return [
            // name, email, subject and body are required
            ['title', 'required', 'message' => '标题不能为空'],
            ['title', 'unique', 'message' => '标题已经存在','on'=>'add'],
            ['content', 'required', 'message' => '内容不能为空'],
            ['keyword', 'required', 'message' => '关键字不能为空'],
            ['title_img', 'required', 'message' => '标题图片不能为空'],
            ['content_introduce', 'required', 'message' => '内容介绍不能为空'],
        ];
    }
    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_ADD] = ['title', 'content','author','keyword','content_introduce','status','title_img'];
        $scenarios[self::SCENARIO_SAVE] = ['title', 'content','author','keyword','content_introduce','status','title_img'];
        return $scenarios;
    }

    /**
     * 更新或添加完成后执行的方法
     * @param bool $insert
     * @param array $changedAttributes
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes); // TODO: Change the autogenerated stub
        if(!empty($this->keyword)){
            $db = \Yii::$app->db;
            $trans=$db->beginTransaction();
            try{
                $Example_uid=\Yii::$app->request->post('Example_uid');
                $keyword=explode(',',$this->keyword);
                //清空标签-文章关联表
                $this->delete_tag_article($this->id);
                $add_tags_id=[];
                //循环查询这个标签有没有
                foreach ($keyword as $v){
                    $tags_id=$this->get_tags($v);
                    if(!empty($tags_id)){
                        $add_tags_id[]=['tag_id'=>$tags_id,'article_id'=>$this->id,'uid'=>$Example_uid,'admin_type'=>1];
                    }else{
                        //不存在就保存
                        $add_id=$this->add_tags($v);
                        $add_tags_id[]=['tag_id'=>$add_id,'article_id'=>$this->id,'uid'=>$Example_uid,'admin_type'=>1];
                    }
                }
                $this->add_tag_article($add_tags_id);
                $trans->commit();
                return true;
            }catch (\Exception $e){
                $trans->rollBack();
               $this->addError('info','保存失败');
               $msg=Message::json_msg(false,1,'保存失败<br/>文章关键字');
               exit($msg);
               return false;
            }

        }


    }

    /**
     * @param $data
     */
    public function add_tag_article($data){
        $cmd = \yii::$app->db;
//        return $cmd->createCommand()->insert(self::TABLE_NAME,['name'=>'cs'])->execute();
        return $cmd->createCommand()->batchInsert('{{%tag_article}}', ['tag_id', 'article_id','uid','admin_type']
            , $data
        )->execute();
    }
    /**
     * 添加标签入库
     * @param $name
     * @return string
     */
    public function add_tags($name){
        \Yii::$app->db->createCommand()->insert('{{%tags}}', [
            'name' => $name,
        ])->execute();
        return \Yii::$app->db->lastInsertID;
    }
    /**
     * 根据标签名字获取标签id
     * @param $name
     * @return mixed
     */
    public function get_tags($name){
        $rows = (new \yii\db\Query())
            ->select(['id'])
            ->from('{{%tags}}')
            ->where(['name'=>$name])
            ->limit(1)
            ->one();
        return $rows['id'];
    }
    /**
     * 删除标签-文章关联表
     * @param $article_id 文章ID
     */
    private function delete_tag_article($article_id){
        \Yii::$app->db->createCommand()->delete('{{%tag_article}}', 'article_id = :article_id',[':article_id' => $article_id])->execute();
    }
    //验证成功的时候处理数据
    public function afterValidate()
    {
        parent::afterValidate(); // TODO: Change the autogenerated stub
        if(empty($this->id)){
            $Example_uid=\Yii::$app->request->post('Example_uid');
            $Example_username=\Yii::$app->request->post('Example_username');
            $this->created_at=time();
            //只有发布的时候才有下面的内容
            //是否是后台发送,0是前台用户发送
            $this->admin_type=0;
            //发布用户的UID
            $this->user_uid=$Example_uid;
            //作者
            $this->author=$Example_username;
            //默认不发布文章
            $this->status=2;
        }else{
            $this->updated_at=time();
        }
        //转移HTML
        $this->content=htmlspecialchars($this->content);
    }


}