<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;

class PostController extends Controller
{
    public function create()
    {
        $recordList = \App\Models\Post::all();
        return view('posts.create',['recordList'=>$recordList]);
    }

    public function store(Request $request)
    {
        Log::info('Proceeding with Storing....');
        $request->validate([
            'title' => 'required|unique:posts|max:255',
            'body'  => 'required',
        ]);

        \App\Models\Post::create([
            'title' => $request->title,
            'body'  => $request->body,
        ]);

        return redirect()
            ->to('posts/create')
            ->with('message', 'The post has been added successfully!');
    }
}
