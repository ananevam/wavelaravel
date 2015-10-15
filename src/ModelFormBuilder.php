<?
namespace Wavelaravel\Wavelaravel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Exception;
use Request;
use Route;
use Lang;

class ModelFormBuilder{
    public $model;
    protected $open_form_options = [];
    protected $prefix_fields_name=null;
    protected $index_field=null;
    protected $relation_name=null;
    /**
     * Constructor
     *
     * @param Model $model
     * @param array $options
     *
     * @return void
     */
    public function __construct(Model $model, $options=[],$prefix_fields_name=null, $index_field=null,$relation_name=null){
        $this->model = $model;
        $this->prefix_fields_name = $prefix_fields_name;
        $this->open_form_options = $options;
        $this->index_field = $index_field;
        $this->relation_name = $relation_name;
    }

    /**
     * Open up a new HTML form.
     *
     * @param  array $options
     *
     * @return string
     */
    public function open($options=[]){
        if (!isset($options['action'])) {
            $route_segment = str_plural($this->getModelName());
            $route = $route_segment . ($this->model->id ? '.update' : '.store');

            if (Route::has($route)) {
                $options['action'] = $this->model->id ? route($route, $this->model) : route($route);
            }
        }
        if (!isset($options['method']) and $this->model->id){
            $options['method'] = 'patch';
        }
        return FormBuilder::open($options);
    }

    public function fields_for($relation_name, $models=null){
        if ($models === null){
            $models = $this->model->$relation_name;
        }

        if ($models instanceof Model){
            yield new self($models, [], $this->getModelName());
        }elseif($models instanceof Collection or is_array($models)){
            $i=0;
            foreach ($models as $model){
                yield new self($model, [], $this->getModelName(), $i, $relation_name);
                $i++;
            }
        }
    }

    public function __invoke()
    {
        if ($this->model->id) {
            return $this->input('hidden', 'id');
        }else{
            return '';
        }
    }

    /**
     * Close the current form.
     *
     * @return string
     */
    public function close(){
        return FormBuilder::close();
    }

    /**
     * Create a form label element.
     *
     * @param  string $for
     * @param  string $value
     * @param  array  $options
     *
     * @return string
     */
    public function label($for, $text=null, $options=[]){
        if ($text === null or $text === false){
            $trans_path = 'validation.attributes.'.$this->getModelName().'.'.$for;
            if (Lang::has($trans_path)){
                $text = trans($trans_path);
            }
        }
        $options = $this->prepareLabelOptions($for, $options);
        return FormBuilder::label($this->getInputId($for), $text, $options);
    }

    /**
     * Create a text input field.
     *
     * @param  string $name
     * @param  array  $options
     *
     * @return string
     */
    public function text($name, $options=[]){
        return $this->input('text', $name, $options);
    }

    /**
     * Create a textarea input field.
     *
     * @param  string $name
     * @param  array  $options
     *
     * @return string
     */
    public function textarea($name, $options=[]){
        $value = $this->getInputValue($name);
        $options = $this->prepareInputOptions($name, $options);
        return FormBuilder::textarea($this->getInputName($name), $value, $options);
    }

    /**
     * Create a hidden input field.
     *
     * @param  string $name
     * @param  array  $options
     *
     * @return string
     */
    public function hidden($name, $options=[]){
        return $this->input('hidden', $name, $options);
    }

    /**
     * Create a password input field.
     *
     * @param  string $name
     * @param  array  $options
     *
     * @return string
     */
    public function password($name, $options=[]){
        $options = $this->prepareInputOptions($name, $options);
        return FormBuilder::input('password', $this->getInputName($name), '', $options);
    }

    /**
     * Create a form input field.
     *
     * @param  string $type
     * @param  string $name
     * @param  array  $options
     *
     * @return string
     */
    public function input($type, $name, $options=[]){
        $value = $this->getInputValue($name, $options);
        $options = $this->prepareInputOptions($name, $options);
        return FormBuilder::input($type, $this->getInputName($name), $value, $options);
    }

    /**
     * Create an e-mail input field.
     *
     * @param  string $name
     * @param  array  $options
     *
     * @return string
     */
    public function email($name, $options=[]){
        return $this->input('email', $name, $options);
    }

    /**
     * Create a file input field.
     *
     * @param  string $name
     * @param  array  $options
     *
     * @return string
     */
    public function file($name, $options=[]){
        $options = $this->prepareInputOptions($name, $options);
        return FormBuilder::file($this->getInputName($name), $options);
    }

    /**
     * Create a checkbox input field.
     *
     * @param  string $name
     * @param  array  $options
     *
     * @return string
     */
    public function checkbox($name, $options=[]){
        $options = $this->prepareInputOptions($name, $options);
        return FormBuilder::checkbox($this->getInputName($name), ($this->getInputValue($name) ? true : false), $options);
    }

    /**
     * Create a radio button input field.
     *
     * @param  string $name
     * @param  mixed  $value
     * @param  array  $options
     *
     * @return string
     */
    public function radio($name, $value, $options=[]){
        $options = $this->prepareInputOptions($name, $options);
        return FormBuilder::radio($this->getInputName($name), $value, $value == $this->getInputValue($name), $options);
    }

    /**
     * Create a select box field.
     *
     * @param  string $name
     * @param  array  $list
     * @param  array  $options
     *
     * @return string
     */
    public function select($name, $list=[], $options=[]){
        $options = $this->prepareInputOptions($name, $options);
        return FormBuilder::select($this->getInputName($name), $list, $this->getInputValue($name), $options);
    }

    /**
     * Create a date input field.
     *
     * @param  string $name
     * @param  array  $options
     *
     * @return string
     */
    public function date($name, $options=[]){
        return $this->input('date',$name, $options);
    }

    /**
     * Create a submit button element.
     *
     * @param  string $value
     * @param  string $name
     * @param  array  $options
     *
     * @return string
     */
    public function submit($value=null, $name=null, $options=[]){
        return FormBuilder::submit($value, $name, $options);
    }

    /**
     * Get the VALUE attribute for a field name.
     *
     * @param  string $name
     * @param  array $options
     *
     * @return string
     */
    protected function getInputValue($name, $options=[]){
        if (isset($options['value'])){
            $value = $options['value'];
            unset($options['value']);
            return $value;
        }else {
            return $this->model->getAttribute($name);
        }
    }

    /**
     * Prepare input options. This method do add ID and not-valid class
     *
     * @param  string $name
     * @param  array $options
     *
     * @return array
     */
    protected function prepareInputOptions($name, $options){
        if (!isset($options['id'])){
            $options['id'] = $this->getInputId($name);
        }
        if (isset($this->model->errors) and
            isset($this->model->errors[$this->getModelName().'.'.$name]) and
            count($this->model->errors[$this->getModelName().'.'.$name]) > 0){
            $options['class'] = isset($options['class']) ? $options['class'] . ' not-valid' : 'not-valid';
        }
        return $options;
    }

    /**
     * Prepare label options. This method do add required class by model rules
     *
     * @param  string $name
     * @param  array $options
     *
     * @return array
     */
    protected function prepareLabelOptions($name, $options){
        if (isset($this->model->validation_rules) and is_array($this->model->validation_rules) and
            isset($this->model->validation_rules[$name]) and
            str_contains($this->model->validation_rules[$name], 'required')){
            $options['class'] = isset($options['class']) ? $options['class'] . ' required' : 'required';
        }
        return $options;
    }

    /**
     * Get the NAME attribute for a field name.
     *
     * @param  string $name
     *
     * @return string
     */
    protected function getInputName($name){
        if ($this->prefix_fields_name){
            return $this->prefix_fields_name .
                '['.($this->relation_name ? $this->relation_name : $this->getModelName()) . ']' .
                (($this->index_field !== null and $this->index_field !== false) ? "[{$this->index_field}]" : '') . "[{$name}]";
        }else {
            return $this->getModelName() . "[{$name}]";
        }
    }

    /**
     * Get the ID attribute for a field name.
     *
     * @param  string $name
     *
     * @return string
     */
    protected function getInputId($name){
        return str_replace(']','',str_replace('[','_',$this->getInputName($name)));
    }

    /**
     * Get model name for field.
     *
     * @return string
     */
    protected function getModelName(){
        return snake_case(class_basename($this->model));
    }

    /**
     * Open form in blade
     *
     * @return string
     */
    public function __toString() {
        return $this->open($this->open_form_options);
    }
}