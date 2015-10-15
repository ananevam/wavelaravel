<?
namespace Wavelaravel\Wavelaravel;
use Validator;

trait ValidatorTrait{
    public $errors = [];

    /**
     * Check valid errors
     *
     * @return bool
     */
    public function hasErrors(){
        return count($this->errors) > 0 ? true : false;
    }

    /**
     * Check valid model
     *
     * @return bool
     */
    public function isValid(){
        $myself = get_called_class();
        if (isset($myself::$rules) and count($myself::$rules) > 0){
            $attrs = [];
            $rules = [];
            foreach ($myself::$rules as $attr=>$rule){
                $attrs[$this->getModelName().'.'.$attr] = $this->getAttribute($attr);
                $rules[$this->getModelName().'.'.$attr] = $rule;
            }
            $validator = Validator::make(
                $attrs,
                $rules
            );
            if ($validator->fails()){
                $this->errors = $validator->messages()->getMessages();
                return false;
            }else{
                $this->errors = [];
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
}