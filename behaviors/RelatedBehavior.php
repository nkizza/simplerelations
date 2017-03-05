<?php
namespace nkizza\simplerelations\behaviors;

use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

class RelatedBehavior extends Behavior
{
    /**
     * @var ActiveRecord
     */
    public $owner;

    /**
     * @var string Model attribute that contain uploaded relations
     */
    public $attribute = '';
	
	/**
	 * @var fields to save
	 */
	public $fields;
	
    /**
     * @var string name of the relation
     */
    public $uploadRelation;

    public $uploadModel;
	
	public $pk;

    /**
     * @var string
     */
    public $uploadModelScenario = 'default';

    /**
     * @return array
     */
    public function events()
    {
        $multipleEvents = [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFindMultiple',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsertMultiple',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdateMultiple',
        ];

        return $multipleEvents;
    }

    /**
     * @return array
     */
    public function fields()
    {
        return $this->fields;
    }

    /**
     * @return void
     */
    public function afterInsertMultiple()
    {
        if ($this->owner->{$this->attribute}) {
            $this->saveToRelation($this->owner->{$this->attribute});
        }
    }

    /**
     * @throws \Exception
     */
    public function afterUpdateMultiple()
    {
		$modelClass = $this->getUploadModelClass();
		
		$pk = $this->pk ?: $modelClass::primaryKey();
		if(is_array($pk) && !empty($pk)) $pk = $pk[0];
		
        $uploadedPks = ArrayHelper::getColumn($this->getUploaded(), $pk);

        $models = $this->owner->getRelation($this->uploadRelation)->all();
        $modelsPks = ArrayHelper::getColumn($models, $pk);
		
		foreach($models as $m) 
			if($m && !in_array($m->{$pk}, $uploadedPks))
				$this->owner->unlink($this->uploadRelation, $m, true); 
		
        $newRecords = $updatedRecords = [];
        foreach ($this->getUploaded() as $record) {
            if (!in_array($record[$pk], $modelsPks)) {
                $newRecords[] 		= $record;
            } else {
                $updatedRecords[] 	= $record;
            }
        }
        $this->saveToRelation($newRecords);
        $this->updateInRelation($updatedRecords);
    }

    /**
     * @return void
     */
    public function afterFindMultiple()
    {
        $models = $this->owner->{$this->uploadRelation};
        $fields = $this->fields();
        $data = [];
        foreach ($models as $k => $model) {
            $entity = [];
            foreach ($fields as $dataField => $modelAttribute) {
                $entity[$dataField] = $model->hasAttribute($modelAttribute)
                    ? ArrayHelper::getValue($model, $modelAttribute)
                    : null;
            }
        }
        $this->owner->{$this->attribute} = $data;
    }

    /**
     * @return string
     */
    public function getUploadModelClass()
    {
        if (!$this->uploadModel) {
            $this->uploadModel = $this->getUploadRelation()->modelClass;
        }
        return $this->uploadModel;
    }

    /**
     * @param array $entities
     */
    protected function saveToRelation($entities)
    {
        $modelClass = $this->getUploadModelClass();
        foreach ($entities as $entity) {
            $model = new $modelClass;
            $model->setScenario($this->uploadModelScenario);
            $model = $this->loadModel($model, $entity);
            if ($this->getUploadRelation()->via !== null) {
                $model->save(false);
            }
            $this->owner->link($this->uploadRelation, $model);
        }
    }

    /**
     * @param array $entities
     */
    protected function updateInRelation($entities)
    {
        $modelClass = $this->getUploadModelClass();
		
		$pk = $this->pk ?: $modelClass::primaryKey();
		if(is_array($pk)) $pk = $pk[0];
		
        foreach ($entities as $entity) {
            $model = $modelClass::findOne([$pk => $entity[$pk]]);
            if ($model) {
                $model->setScenario($this->uploadModelScenario);
                $model = $this->loadModel($model, $entity);
                $model->save(false);
            }
        }
    }

    /**
     * @return array
     */
    protected function getUploaded()
    {
        $entities = $this->owner->{$this->attribute};
        return $entities ?: [];
    }

    /**
     * @return \yii\db\ActiveQuery|\yii\db\ActiveQueryInterface
     */
    protected function getUploadRelation()
    {
        return $this->owner->getRelation($this->uploadRelation);
    }

    /**
     * @param $model \yii\db\ActiveRecord
     * @param $data
     * @return \yii\db\ActiveRecord
     */
    protected function loadModel(&$model, $data)
    {
    	$attributes = array_keys($model->attributes);
        foreach ($this->fields() as $attribute) {
            if ($attribute && in_array($attribute, $attributes)) {
                $model->{$attribute} =  ArrayHelper::getValue($data, $attribute);
            }
        }
        return $model;
    }

    /**
     * @param $type
     * @return mixed
     */
    protected function getAttributeField($type)
    {
        return ArrayHelper::getValue($this->fields(), $type, false);
    }
}
