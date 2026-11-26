<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\IncomeCategory;

class IncomeCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $title="Income Categories";
        $categories = IncomeCategory::get();
        return view('income-categories',compact('title','categories'));
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
        IncomeCategory::create($request->only('name'));
        $notification=array(
            'message'=>"Income Category has been added !!!",
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
        $category = IncomeCategory::findOrFail($request->id);
        $category->update([
            'name'=>$request->name,
        ]);
        $notification=array(
            'message'=>"Income Category has been updated !!!",
            'alert-type'=>'success'
        );
        return back()->with($notification);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $category = IncomeCategory::findOrFail($request->id);
        if ($category->incomes()->withTrashed()->exists()) return back()->with('message', 'This category has recorded income and must be retained.')->with('alert-type', 'warning');
        $category->delete();
        $notification=array(
            'message'=>"Income Category has been deleted !!!",
            'alert-type'=>'success'
        );
        return back()->with($notification);
    }
}
