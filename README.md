# wavelaravel/wavelaravel

Features: self model validation, form model binding(including relations), STI pattern.

## Installation

`composer.json`

``` json
"repositories": [
  {
    "type": "vcs",
    "url": "https://bitbucket.org/Minoru/wavelaravel"
  }
]
```
``` json
"require": {
  "wavelaravel/wavelaravel": "dev-master"
}
```

And run `composer update`

## Usage
You must extend your classes from Wavelaravel\Wavelaravel\WaveModel
``` php
use Wavelaravel\Wavelaravel\WaveModel;

class Post extends WaveModel  {
}
```
## Validation
Declare validation rules in array `$validation_rules`.

You can create custom validation rules the same way you would for the Laravel Validator.
``` php
class Post extends WaveModel  {
    protected $fillable = ['name', 'preview', 'body'];
    public $validation_rules = [
        'name'=>'required',
    ];
}
```

## Model Hooks
Here's the complete list of available hooks:

- `before`/`afterCreate()`

- `before`/`afterSave()`

- `before`/`afterUpdate()`

- `before`/`afterDelete()`


## Relations
Wavelaravel don't support many to many relation, but you can use default laravel method.

Relation rules to declare in array `relations_rules`
If you add flag `'validate'=>true` in relation rule, validator will check relation models validation.


Sometimes we don't need to save relation model if all its fields are empty. In such case you can add parameter reject_if and declare static check function.

Example:

``` php
class Post extends WaveModel  {
    protected $fillable = ['name', 'preview', 'body','comments', 'images'];

    public $validation_rules = [
        'name'=>'required',
    ];
    public $relations_rules = array(
        'comments'  => [
            self::HAS_MANY, 'App\Models\Comment', 'validate'=>true
        ],
        'images' => [
            self::MORPH_MANY,
            'App\Models\Image',
            'owner',
            'validate' => true,
            'reject_if' => 'rejectIf',
        ],
    );
    public static function rejectIf($model){
        if ($model->file_file_name == '' and $model->name == '' and !$model->id){
            return true;
        }else{
            return false;
        }
    }
}
```

## Form Model Binding
Example views form
posts/create.blade.php
``` php
@extends('layouts.master')
@section('content')
    <h1>Create New Post</h1>
    <hr/>
    @include('posts/_form')
@endsection
```
posts/edit.blade.php
``` php
@extends('layouts.master')
@section('content')
    <h1>Edit Post</h1>
    <hr/>
    @include('posts/_form')
@endsection
```
posts/_form.blade.php
``` php
{!! $f = form_for($post, ['class'=>'form-horizontal']) !!}
@if ($post->hasErrors())
    <p class="bg-danger">
        {!! implode('<br>', array_dot($post->errors)) !!}
    </p>
@endif
<div class="form-group">
    {!! $f->label('name', null, ['class' => 'col-sm-3 control-label']) !!}
    <div class="col-sm-6">
        {!! $f->text('name', ['class' => 'form-control']) !!}
    </div>
</div>
<div class="form-group">
    <div class="col-sm-offset-3 col-sm-3">
        {!! $f->submit('Save',null, ['class' => 'btn btn-primary form-control']) !!}
    </div>
</div>
{!! $f->close() !!}
```
You can use `fields_for` for relation models

Example:
```php
@foreach ($f->fields_for('comments') as $cf)
    {!! $cf() !!}
    <div class="form-group">
        {!! $cf->label('name', null, ['class' => 'col-sm-3 control-label']) !!}
        <div class="col-sm-6">
            {!! $cf->text('name', ['class' => 'form-control']) !!}
        </div>
    </div>
@endforeach
``` 
Method `{!! $cf() !!}` out model id attribute if it exists. Controller changes for it? Nothing. Only add `comments` in fillable fields
`protected $fillable = ['name', 'preview', 'body','comments'];`

Since wavelaravel uses self model validation we dont't need to use redirect withErrors, use like ruby on rails method, model field `errors` required errors messages

Example controller
``` php
class PostController extends Controller {
	public function index() {
		$posts = Post::all();
		return view('posts.index', compact('posts'));
	}
	public function create() {
		$post = new Post();
        return view('posts.create', compact('post'));
	}
	public function store(Request $request) {
        $post = new Post($request->input('post'));
        if ($post->save()){
            return redirect()->route('posts.show',$post);
        }else {
            return view('posts.create', compact('post'));
        }
	}
	public function show($id) {
		$post = Post::findOrFail($id);
		return view('posts.show', compact('post'));
	}
	public function edit($id) {
		$post = Post::findOrFail($id);
		return view('posts.edit', compact('post'));
	}
	public function update($id, Request $request) {
		$post = Post::findOrFail($id);
		if ($post->update($request->input('post'))){
            return redirect()->route('posts.show', $post);
        }else{
            return view('posts.edit', compact('post'));
        }

	}
	public function destroy($id) {
        Post::destroy($id);
		return redirect()->route('posts.index');
	}
}
```

If you use resource for declare routing for model, wavelaravel will guess routing.
``` php
Route::resource('posts', 'PostController');
```

Else you can substitute its route

Example create:
```php
@section('content')
    {!! $f = form_for($model, ['action' => route("admin.news.store"), 'files'=>true, 'class'=>'form-horizontal']) !!}
    @include("admin/news/_form")
    {!! $f->close() !!}
@endsection
```

Example edit:
```php
@section('content')
    {!! $f = form_for($model, ['action' => route("admin.news.update", [$model->id]), 'files'=>true, 'class'=>'form-horizontal']) !!}
    @include("admin/news/_form")
    {!! $f->close() !!}
@endsection
```

## STI pattern
Just add field `sti_field` and create migration with string field.

Example:
```php
class Post extends WaveModel  {
    public $sti_field = 'class_name';
}
```
```php
class SecondPost extends Post  {
}
```