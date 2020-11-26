<?php

namespace App\Admin\Controllers;

use Encore\Admin\Controllers\AdminController;
use App\Models\Category;
use App\Models\Product;
use Encore\Admin\Grid;
use Encore\Admin\Form;

abstract class CommonProductsController extends AdminController
{
    // 定义一个抽象方法，返回当前管理的商品类型
    abstract public function getProductType();

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Product);
        
        // 使用 with 来预加载商品类目数据，减少sql查询
        $grid->model()->where('type', $this->getProductType())->orderBy('id', 'desc');
        
        // 调用自定义的方法
        $this->customGrid($grid);

        $grid->actions(function ($actions) {
            $actions->disableView();
            $actions->disableDelete();
        });
        $grid->tools(function ($tools) {
            // 禁用批量删除按钮
            $tools->batch(function ($batch) {
                $batch->disableDelete();
            });
        });

        return $grid;
    }

    // 定义一个抽象方法，各个类型控制器将实现本方法来定义列表应该展示哪些字段
    abstract protected function customGrid(Grid $grid);

      /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Product);

        // 在表单中添加一个名为 type, 值为 Product::TYPE_CROWDFUNDING 的隐藏字段
        $form->hidden('type')->value(Product::TYPE_CROWDFUNDING);
        $form->text('title', '商品名称')->rules('required');
        $form->select('category_id', '类目')->options(function($id){
            $category = Category::find($id);
            if ($category) {
                return [$category->id => $category->full_name];
            }
        })->ajax('/admin/api/categories?is_directory=0');
        $form->image('image', '封面图片')->rules('required|image');
        $form->quill('description', '商品描述')->rules('required');
        $form->radio('on_sale', '上架')->options(['1' => '是', '0' => '否'])->default('0');

        // 调动自定义方法
        $this->customForm($form);

        $form->hasMany('skus', '商品SKU', function(Form\NestedForm $form){
            $form->text('title', 'SKU 名称')->rules('required');
            $form->text('description', 'SKU 描述')->rules('required');
            $form->text('price', '单价')->rules('required|numeric|min:0.01');
            $form->text('stock', '剩余库存')->rules('required|integer|min:0');
        });
        $form->saving(function(Form $form){
            $form->model()->price = collect($form->input('skus'))->where(Form::REMOVE_FLAG_NAME, 0)->min('price');
        });

        return $form;
    }

    // 定义一个抽象方法,各个类型的控制器将实现本方法来定义表单有哪些额外字段
    abstract protected function customForm(Form $form);

}