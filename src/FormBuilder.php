<?
namespace Wavelaravel\Wavelaravel;
use Request;

class FormBuilder {
    /**
     * The form methods that should be added as hidden input.
     *
     * @var array
     */
    protected static $fake_methods = ['PATCH','PUT','DELETE'];

    /**
     * Open up a new HTML form.
     *
     * @param  array $options
     *
     * @return string
     */
    public static function open($options=[]){
        $method = array_get($options, 'method', 'post');
        $method = strtoupper($method);
        $options['method'] = ($method == 'POST' or in_array($method, self::$fake_methods)) ? 'POST' : 'GET';
        if (isset($options['files'])){
            if ($options['files']===true) {
                $options['enctype'] = 'multipart/form-data';
            }
            unset($options['files']);
        }

        if (!isset($options['action'])) {
            $options['action'] = Request::url();
        }
        $options['accept-charset'] = 'UTF-8';

        return '<form'.self::getHtmlParamsFromOptions($options).'>'.csrf_field().self::getAppendMethod($method);
    }

    /**
     * Added hidden method if his is patch/put/delete
     *
     * @param string $method
     *
     * @return string
     */
    protected static function getAppendMethod($method){
        if (in_array($method, self::$fake_methods)){
            return self::input('hidden', '_method', $method);
        }else{
            return '';
        }
    }

    /**
     * Close the current form.
     *
     * @return string
     */
    public static function close(){
        return '</form>';
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
    public static function label($for, $value=null, $options=[]){
        if ($value === null or $value === false){
            $value = studly_case($for);
        }
        $options['for'] = $for;

        return '<label'.self::getHtmlParamsFromOptions($options).'>'.$value.'</label>';
    }

    /**
     * Create a form input field.
     *
     * @param  string $type
     * @param  string $name
     * @param  string $value
     * @param  array  $options
     *
     * @return string
     */
    public static function input($type, $name, $value, $options=[]){
        return '<input type="'.$type.'" name="'.$name.'" value="'.$value.'"'.self::getHtmlParamsFromOptions($options).'>';
    }

    /**
     * Create a date input field.
     *
     * @param  string $name
     * @param  string $value
     * @param  array  $options
     *
     * @return string
     */
    public static function date($name, $value, $options=[]){
        if($value instanceof \DateTime or $value instanceof \Carbon\Carbon) {
            $value = $value->format('Y-m-d');
        }
        return self::input('date', $name, $value, $options);
    }

    /**
     * Create a textarea input field.
     *
     * @param  string $name
     * @param  string $value
     * @param  array  $options
     *
     * @return string
     */
    public static function textarea($name, $value, $options=[]){
        return '<textarea name="'.$name.'"'.self::getHtmlParamsFromOptions($options).'>'.$value.'</textarea>';
    }

    /**
     * Create a file input field.
     *
     * @param  string $name
     * @param  array  $options
     *
     * @return string
     */
    public static function file($name, $options=[]){
        return '<input type="file" name="'.$name.'"'.self::getHtmlParamsFromOptions($options).'>';
    }

    /**
     * Create a checkbox input field.
     *
     * @param  string $name
     * @param  bool   $checked
     * @param  array  $options
     *
     * @return string
     */
    public static function checkbox($name, $checked=null, $options=[]){
        $hidden = self::input('hidden', $name, 0);
        $checkbox = '<input type="checkbox" name="'.$name.'" value="1"'.($checked ? ' checked' : '').self::getHtmlParamsFromOptions($options).'>';
        return $hidden . "\r\n" . $checkbox;
    }

    /**
     * Create a radio button input field.
     *
     * @param  string $name
     * @param  mixed  $value
     * @param  bool   $checked
     * @param  array  $options
     *
     * @return string
     */
    public static function radio($name, $value, $checked=null, $options=[]){
        return '<input type="radio" name="'.$name.'" value="'.$value.'"'.($checked ? ' checked' : '').self::getHtmlParamsFromOptions($options).'>';
    }

    /**
     * Create a select box field.
     *
     * @param  string $name
     * @param  array  $list
     * @param  string $selected
     * @param  array  $options
     *
     * @return string
     */
    public static function select($name, $list=[], $selected = null, $options=[]){
        $select = '<select name="'.$name.'"'.self::getHtmlParamsFromOptions($options).">\r\n";
        if (isset($options['include_blank']) and $options['include_blank']===true){
            unset($options['include_blank']);
            $select .= '<option value=""></option>';
        }
        foreach ($list as $value){
            $val = '';
            $text='';
            if (is_array($value)) {
                if (count($value) > 1) {
                    list($val, $text) = $value;
                }elseif(count($value) == 1){
                    $val = $value[0];
                    $text = $value[0];
                }
            }else{
                $val = $value;
                $text = $value;
            }
            $select .= '<option value="'.$val.'"'.($selected == $val ? ' selected':'').'>'.$text."</option>\r\n";
        }
        return $select . '</select>';
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
    public static function submit($value=null, $name=null, $options=[]){
        if ($name === null){
            $name = 'commit';
        }
        if ($value === null){
            $value = 'Commit';
        }
        return self::input('submit', $name, $value, $options);
    }

    protected static function getHtmlParamsFromOptions($options=[]){
        $result = [];
        foreach ($options as $name=>$value){
            $result[] = "{$name}=\"{$value}\"";
        }
        return (count($result) > 0 ? ' ':'') . implode(' ', $result);
    }
}