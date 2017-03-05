Simplerelations for Yii2
====================================

Provides simple admin tool to manage relations. Configurate needed relation with RelatedBehavior, add RelatedWidget to your form and enjoy the easy relation management.

Installation
------------
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require nkizza/simplerelations:~1.0
```
or add

```json
"nkizza/simplerelations" : "~1.0"
```

to the require section of your application's `composer.json` file.

Usage
-----
This extension comes with two possibilities: 
1. Behavior that serves to manage related active records. You have to provide a specific 
attribute for that records and configure validation rules first; then just add the behavior 
to your model.
2. Simple widget that serves to display your related records and provide functionality to 
add, edit and delete them. Widget is simply configurable and lets you create flexible forms 
for your related records. 
 
**RelatedBehavior**

First, add the specific public attribute for your related records and include it into your
model. Then, configure the behavior:

```php
class Match extends \yii\db\ActiveRecord
{
    public $_players;
	
    public function behaviors() {
        return [
            [
                'class' => \nkizza\simplerelations\RelatedBehavior::className(),
                'attribute' => '_players', //attribute which stores the related data
                'uploadRelation' => 'players', //relation name
                'uploadModelScenario' => 'default', //you can provide the specific scenario name for the related models
                'fields' => [   //fields of related record that we manage using this behavior
                    'id_player', 'min_played', 'goals', 'assists', 'y_cards', 'r_cards',            
                ],
            ],
        ];
    }
	
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['_players'], 'safe'],
        ];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPlayers()
    {
        return $this->hasMany(PlayerMatch::className(), ['id_match' => 'id']);
    }
    
```

**RelatedWidget**

Related widget helps to manage related records. It allows you to build flexible forms to 
edit related records within the current model form. 

***Example of use***  
Related widget extends `\yii\widgets\InputWidget`. There are two ways of using it, with 
an `ActiveForm` instance or as a widget setting up its `model` and `attribute`.

```php
<?php
use nkizza\simplerelations\RelatedWidget;
?>

<h3>Players of match</h3>
<?= RelationWidget::widget([
	//current owner model, required
    'model' => $model,
     //creates a labels for related fields. Useful for table-like view.
    'labels' => true,  
    //attribute to store related widgets, required
    'attribute' => '_players',  
    //relation name, required
    'relation' => 'players',    
    //addititional condition to get related models. Added to relation query, optional
    'condition' => ['home' => 1], 
    //container options, optional
    'options' => ['class' => 'players-table'], 
    //each fields row container options, optional
    'fieldOptions' => ['class' => 'form-inline mb10'], 
    'fields' => [
        [
        	//related record attribute
            'attribute' => 'home', 
            //Html helper method
            'method' => 'hiddenInput',
            //preset value. If not set, related record value is used.
            'value' => 1,  
        ],
        [
            'attribute' => 'id_player',
            'method' => 'dropDownList',
            //items for dropDownList
            'items' => ArrayHelper::map(\app\models\Player::find()->orderBy(['surname' => SORT_ASC])->all(), 'id', 'fullname'),   
        ],
        //simple text field for attribute `min_played`
        'min_played', 
        
        //Wrapper. Usually used to wrap some fields into a dropdown or another container. 
        //You need to configurate it with `html` option and add `{{content}}` variable into your html to place the fields.
        //`fields` option is configurated similar to the whole widget (dropdowns, hidden fields, etc).
        [
        	//wrapper label
            'label' => 'Goals and assists dropdown',
            //required for wrapper method
            'method' => 'wrapper',
            //container options
            'wrapperOptions' => ['class' => 'dropdown form-group goals'],
            //required for wrapper. Wrapper template, don't forget to insert {{content}} somewhere
            'html' => '
                <button id="drop_goals" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="btn btn-info">
                    Goals <span data-attr="goals"></span> <span class="caret"></span>
                </button>
                <ul class="dropdown-menu" aria-labelledby="drop_goals">{{content}}</ul>
            ',
            //fields to wrap
            'fields' => [ 
                'goals', 'assists', 
            ]
        ],
        [
            'attribute' => 'y_cards',
            //other simple Html helper methods are allowed too.
            'method' => 'textarea', 
        ],
    ]
]);?>
```

You can use these classes together or separately. Together they make a complete solution to
save simple relations to your model; separately, you need to provide client or server part 
to save them. 