<?
namespace Wavelaravel\Wavelaravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
#  Relation many collection class
use Illuminate\Database\Eloquent\Collection;
use Exception;
use Validator;
use Illuminate\Database\Eloquent\Relations\Relation;

class WaveModel extends Model  {
    public $errors = [];
    const BELONGS_TO = 'belongsTo';
    const HAS_MANY = 'hasMany';
    const MORPH_TO = 'morphTo';
    const MORPH_MANY = 'morphMany';
    const HAS_ONE = 'hasOne';

    # flag for destroy checkbox in form
    protected $_destroy = 0;

    # save without validations
    protected $force_save = false;

    # sti pattern field
    public $sti_field = null;

    # change to false in setAttribute, need for exclude cicle save model->relation->model
    public $is_saved = true;

    # declaration relations
    public $relations_rules = [];

    # relation models
    protected $relations_data = [
        self::BELONGS_TO => [],
        self::HAS_MANY => [],
        self::MORPH_TO => [],
        self::MORPH_MANY => [],
        self::HAS_ONE => [],
    ];

    # validation rules
    public $validation_rules = [];

    /**
     * Constructor
     *
     * @param array $attributes
     *
     * @return void
     */
    public function __construct($attributes = array()) {
        parent::__construct($attributes);

        if ($this->sti_field) {
            $this->setAttribute($this->sti_field, get_class($this));
        }
    }

    /**
     * Check valid errors
     *
     * @return bool
     */
    public function hasErrors(){
        return count($this->errors) > 0 ? true : false;
    }

    /**
     * Redefined for STI pattern
     *
     * @return mixed
     */
    public function newQuery() {
        $builder = parent::newQuery();

        if ($this->sti_field) {
            $builder->where($this->sti_field, get_class($this));
        }

        return $builder;
    }

    /**
     * Register callbacks after/before Save and other
     *
     * @return void
     */
    public static function boot() {
        parent::boot();
        $myself   = get_called_class();
        $hooks    = array('before' => 'ing', 'after' => 'ed');
        $radicals = array('sav', 'validat', 'creat', 'updat', 'delet');
        foreach ($radicals as $rad) {
            foreach ($hooks as $hook => $event) {
                $method = $hook.ucfirst($rad).'e';
                if (method_exists($myself, $method)) {
                    $eventMethod = $rad.$event;
                    self::$eventMethod(function($model) use ($method){
                        return $model->$method($model);
                    });
                }
            }
        }
    }

    /**
     * Check model and relations validation and set foreign key for belongsTo
     *
     * @return bool
     */
    public function beforeSave(){
        if ($this->isValid()) {
            foreach ($this->relations_data[self::BELONGS_TO] as $relation_name=>$model){
                $this->saveRelationModelBeforeSave($relation_name, $model);
                $relation = $this->getRelationObject($relation_name);
                $this->setAttribute($relation->getForeignKey(), $model->getAttribute($model->getKeyName()));
            }
            foreach ($this->relations_data[self::MORPH_TO] as $relation_name=>$model){
                $this->saveRelationModelBeforeSave($relation_name, $model);
                $this->{$relation_name.'_type'} = get_class($model);
                $this->{$relation_name.'_id'} = $model->id;
            }
            return true;
        }
        return false;
    }

    /**
     * Save relation model before save this
     *
     * @param string $relation_name
     * @param Model $model
     *
     * @return void
     */
    protected function saveRelationModelBeforeSave($relation_name, Model $model){
        if ($model->is_saved == false) {
            if (array_get($this->relations_rules[$relation_name], 'validate', false)) {
                $model->save();
            } else {
                $model->forceSave();
            }
        }
    }

    /**
     * Save without validation
     *
     * @return mixed
     */
    public function forceSave(){
        $this->force_save = true;
        return $this->save();
    }

    /**
     * Save has Many relations
     *
     * @return void
     */
    public function afterSave(){
        $this->is_saved=true;
        # save has many and morph many relations
        $relation_types = [self::HAS_MANY, self::MORPH_MANY, self::HAS_ONE];
        foreach ($relation_types as $relation_type) {
            foreach ($this->relations_data[$relation_type] as $relation_name=>$models) {
                if ($relation_type == self::HAS_ONE){
                    $models = array($models);
                }
                foreach ($models as $k=>$model){
                    if ($model->_destroy) {
                        if ($model->id) $model->delete();
                        if ($relation_name != self::HAS_ONE) {
                            unset($this->relations_data[$relation_type][$relation_name][$k]);
                        }
                    }elseif($this->isReject($relation_type, $model)===false){
                        if (in_array($relation_type, [self::HAS_MANY, self::HAS_ONE])) {
                            $relation = $this->getRelationObject($relation_name);
                            $foreign_key = last(explode('.', $relation->getForeignKey()));
                            $model->setAttribute($foreign_key, $this->getAttribute($this->getKeyName()));
                        }
                        if (array_get($this->relations_rules[$relation_name], 'validate', false)) {
                            $model->save();
                        } else {
                            $model->forceSave();
                        }
                    }
                }
            }
        }
        $this->clearRelations();
    }

    public function beforeCreate(){}
    public function afterCreate(){}
    public function beforeUpdate(){}
    public function afterUpdate(){}
    public function beforeDelete(){}
    public function afterDelete(){}

    /**
     * Clear relations data after save has_many/morph_many relations
     *
     * @return void
     */
    protected function clearRelations(){
        foreach ($this->relations_data as $relation_name=>$val){
            $this->relations_data[$relation_name] = [];
        }
    }

    /**
     * Return attribute or relation model if his exists
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getAttribute($name){
        if (isset($this->relations_rules[$name])){
            list($relation_method) = $this->relations_rules[$name];

            if (in_array($relation_method, [self::BELONGS_TO, self::MORPH_TO, self::HAS_ONE])){
                return $this->getRelationOne($name);
            }elseif ($relation_method === self::HAS_MANY or $relation_method === self::MORPH_MANY){
                return $this->getRelationMany($name);
            }
        }elseif($name == '_destroy'){
            # for destroy checkbox
            return $this->_destroy;
        }

        return parent::getAttribute($name);
    }

    /**
     * Get belongs_to/morph_to model
     *
     * @param string $name
     *
     * @return Model
     */
    protected function getRelationOne($name){
        list($relation_method) = $this->relations_rules[$name];
        if (isset($this->relations_data[$relation_method][$name])){
            return $this->relations_data[$relation_method][$name];
        }else{
            return $this->getRelationObject($name)->first();
        }
    }

    /**
     * Get has_many/morph_many models
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function getRelationMany($name){
        list($relation_method) = $this->relations_rules[$name];
        if (isset($this->relations_data[$relation_method][$name]) and count($this->relations_data[$relation_method][$name]) > 0){
            $relation_data_ids=[];
            foreach ($this->relations_data[$relation_method][$name] as $model){
                if ($model->id) {
                    $relation_data_ids[] = $model->id;
                }
            }
            $models = $this->getRelationObject($name)->whereNotIn('id',$relation_data_ids)->get();

            foreach ($this->relations_data[$relation_method][$name] as $model){
                $models->add($model);
            }
            return $models;
        }else{
            return $this->getRelationObject($name)->get();
        }
    }

    /**
     * Set attribute or relation if him exists
     *
     * @param string $name
     * @param mixed $value
     *
     * @return void
     */
    public function setAttribute($name, $value){
        $this->is_saved = false;

        if (isset($this->relations_rules[$name])) {
            list($relation_method) = $this->relations_rules[$name];

            switch($relation_method){
                case self::BELONGS_TO:
                case self::MORPH_TO:
                case self::HAS_ONE:
                    $this->setRelationOne($name, $value);
                    break;
                case self::HAS_MANY:
                case self::MORPH_MANY:
                    $this->setRelationMany($name, $value);
                    break;

            }
        }elseif($name == '_destroy') {
            $this->_destroy = $value;
        }else{
            parent::setAttribute($name,$value);
        }
    }

    /**
     * Set belongs_to/morph_to relation models
     *
     * @param string $name
     * @param mixed $value
     *
     * @return mixed
     */
    protected function setRelationOne($name, $value){
        list($relation_method) = $this->relations_rules[$name];

        if ($value instanceof Model){
            $model = $value;
        }elseif (is_array($value)){
            if (in_array($relation_method, [self::BELONGS_TO, self::HAS_ONE])) {
                $model_class = $this->getRelationObject($name)->getRelated();
            }else{ # morph to
                $model_class = $value[$this->getRelationObject($name)->getMorphType().'_type'];
            }
            $primary_key_name = $model_class->getKeyName();
            if (isset($value[$primary_key_name])){
                $model = $model_class::find($value[$primary_key_name]);
                $model->fill($value);
            }else {
                $model = new $model_class($value);
            }
        }elseif ($value == null) {
            $model=null;
        }else{
            throw new \Exception('Incorrect relation value');
        }
        $this->relations_data[$relation_method][$name] = $model;
    }

    /**
     * Set has_many/morph_many relation models
     *
     * @param string $name
     * @param mixed $value
     *
     * @return mixed
     */
    protected function setRelationMany($name, $values){
        list($relation_method) = $this->relations_rules[$name];

        $relation = $this->getRelationObject($name);
        $model_class = $relation->getRelated();
        $this->relations_data[$relation_method][$name] = [];
        $primary_key_name = $model_class->getKeyName();

        foreach ($values as $value) {
            $model = null;
            if (is_array($value)) {
                if (isset($value[$primary_key_name])) {
                    $model = $model_class::find($value[$primary_key_name]);
                    $model->fill($value);
                }else {
                    $model = new $model_class($value);
                }
            }elseif($value instanceof Model) {
                $model = $value;
            }else{
                throw new \Exception('Incorrect relation value');
            }
            if ($model != null){
                if ($relation_method == self::MORPH_MANY){
                    list($relation_method, $relation_model, $relation_key) = $this->relations_rules[$name];
                    $model->setAttribute($relation_key, $this);
                }else{ # HAS MANY
                    $model->setAttribute($this->getModelName($this), $this);
                }
            }

            $this->relations_data[$relation_method][$name][] = $model;
        }
    }

    /**
     * Get relation object
     *
     * @param string $name
     *
     * @return Relation
     */
    protected function getRelationObject($name){
        list($relation_method) = $this->relations_rules[$name];

        if ($relation_method == self::BELONGS_TO) {
            list($relation_method, $relation_model) = $this->relations_rules[$name];
            $other_key = array_get($this->relations_rules[$name],'other_key', 'id');
            $foreign_key = array_get($this->relations_rules[$name],'foreign_key', str_singular($name) . '_id');

            return $this->$relation_method($relation_model, $foreign_key, $other_key, $name);
        }elseif(in_array($relation_method, [self::HAS_MANY, self::HAS_ONE])) {
            list($relation_method, $relation_model) = $this->relations_rules[$name];
            $local_key = array_get($this->relations_rules[$name],'local_key', 'id');
            $foreign_key = array_get($this->relations_rules[$name],'foreign_key', $this->getModelName() . '_id');

            return $this->$relation_method($relation_model, $foreign_key, $local_key);
        }elseif($relation_method == self::MORPH_TO) {
            return $this->$relation_method($name);
        }elseif($relation_method == self::MORPH_MANY){
            list($relation_method, $relation_model, $field_name) = $this->relations_rules[$name];
            return $this->$relation_method($relation_model, $field_name);
        }else{
            throw new Exception("Invalid relation type " . $relation_method);
        }
    }

    /**
     * Check valid model with relations
     *
     * @return bool
     */
    public function isValid(){
        $this->errors = [];

        $is_valid = $this->force_save ? true : $this->isModelAndRelationsValid();
        $this->force_save = false;
        return $is_valid;
    }

    /**
     * Check valid model and relations
     *
     * @return bool
     */
    protected function isModelAndRelationsValid(){
        $is_model_valid = $this->isModelValid();
        $is_relations_valid = $this->isRelationsValid();
        return ($is_model_valid and $is_relations_valid);
    }

    /**
     * Check valid model
     *
     * @return bool
     */
    public function isModelValid(){
        if ($this->force_save){
            $this->force_save=false;
            return true;
        }
        if (isset($this->validation_rules) and count($this->validation_rules) > 0){
            $attrs = [];
            $rules = [];
            foreach ($this->validation_rules as $attr=>$rule){
                $attrs[$this->getModelName() . '.' . $attr] = $this->getAttribute($attr);
                $rules[$this->getModelName() . '.' . $attr] = $rule;
            }

            $validator = Validator::make(
                $attrs,
                $rules
            );
            if ($validator->fails()){
                $this->errors = $validator->messages()->getMessages();
                return false;
            }
        }
        return true;
    }

    /**
     * Check valid relations if option validate exists
     *
     * @return bool
     */
    protected function isRelationsValid() {
        $is_relations_valid = true;

        foreach ($this->relations_data as $relation_type=>$relations){
            foreach ($relations as $relation_name => $models){
                $_models = [];
                if ($models instanceof Model) {
                    $_models = [$models];
                } elseif (is_array($_models)) {
                    $_models = $models;
                } else {
                    throw new Exception("Invalid model in {__FILE__} in {__LINE__}");
                }
                foreach ($_models as $model) {
                    if (array_get($this->relations_rules[$relation_name], 'validate', false) and
                        $this->isReject($relation_name, $model) === false
                    ) {
                        $is_relations_valid = ($is_relations_valid == true ? $this->isRelationModelValid($model) : false);
                    }
                }
            }
        }

        return $is_relations_valid;
    }

    /**
     * Reject model if
     *
     * @param string $relation_name
     * @param Model $model
     *
     * @return bool
     */
    protected function isReject($relation_name, $model){
        if (isset($this->relations_rules[$relation_name]['reject_if'])){
            $func = $this->relations_rules[$relation_name]['reject_if'];

            if (static::$func($model)){
                return true;
            }
        }
        return false;
    }

    /**
     * Check valid relation model
     *
     * @param Model $model
     *
     * @return bool
     */
    protected function isRelationModelValid($model){
        if (method_exists($model, 'isModelValid') and !$model->_destroy) {
            if (!$model->isModelValid()) {
                if (!isset($this->errors['relations'])) {
                    $this->errors['relations'] = [];
                }
                $model_name = snake_case(class_basename($model));
                if (count($model->errors) > 0){
                    if (!isset($this->errors['relations'][$model_name])){
                        $this->errors['relations'][$model_name] = [];
                    }
                }
                $this->errors['relations'][$model_name] = array_merge($this->errors['relations'][$model_name],$model->errors);

                return false;
            }
        }
        return true;
    }

    /**
     * Model name
     *
     * @return string
     */
    protected function getModelName(){
        return snake_case(class_basename($this));
    }

    /**
     * Return relations
     *
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments) {
        if (isset($this->relations_rules[$name])){
            return $this->getRelationObject($name);
        }else {
            return parent::__call($name, $arguments);
        }
    }

}
