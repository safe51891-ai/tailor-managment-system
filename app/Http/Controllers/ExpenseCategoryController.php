<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExpenseCategory;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $title="expense Categories";
        $categories = ExpenseCategory::get();
        return view('expense-categories',compact('title','categories'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request,[
            'name'=>'required|string|max:200',
        ]);
        ExpenseCategory::create($request->only('name'));
        $notification=array(
            'message'=>"Expense Category has been added !!!",
            'alert-type'=>'success'
        );
        return back()->with($notification);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $this->validate($request,[
            'name'=>'required|string|max:200',
        ]);
        $request->validate(['id' => 'required|integer']);
        $category = ExpenseCategory::findOrFail($request->id);
        $category->update([
            'name'=>$request->name,
        ]);
        $notification=array(
            'message'=>"Expense Category has been updated !!!",
            'alert-type'=>'success'
        );
        return back()->with($notification);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $category = ExpenseCategory::findOrFail($request->id);
        if ($category->is_salary || $category->expenses()->exists()) return back()->with('message', 'Keep the salary category and categories with recorded expenses.')->with('alert-type', 'warning');
        $category->delete();
        $notification=array(
            'message'=>"Expense Category has been deleted !!!",
            'alert-type'=>'success'
        );
        return back()->with($notification);
    }
}
