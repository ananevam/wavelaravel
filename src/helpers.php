<?
use Wavelaravel\Wavelaravel\ModelFormBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * Helper for create ModelFormBuilder
 *
 * @param Model $model
 * @param array $options
 *
 * @return ModelFormBuilder
 */
function form_for(Model $model, $options=[]){
    return new ModelFormBuilder($model, $options);
}