<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\ConsensusForming;
use App\Models\Like;
use App\Models\Option;
use App\Models\UserOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConsensusFormingController extends Controller
{

    public function list($count,$user_id)
    {
        if($user_id==0){
            $consensus_forming = ConsensusForming::withCount('comments', 'likes')->with('comments', 'options')->limit($count)->get();
        }
        else{
            $consensus_forming = ConsensusForming::withCount('comments', 'likes')->with('comments', 'options')->where('user_id',$user_id)->limit($count)->get();
        }
        return response()->json($consensus_forming);
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:255',
            'description' => 'required',
            'address' => 'required',
            'latitude' => 'required',
            'longitude' => 'required',
            'audience' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'user_id' => 'required',
            'participation' => 'required',
            'vote_question' => 'required',
        ]);
        if ($validator->fails()) {
            $messages = $validator->messages()->all();

            return response()->json([
                'status' => 'Error',
                'message' => $messages[0],
            ], 200);
        }

        if (isset($request->id)) {
            $consensus_forming = ConsensusForming::find($request->id);
        } else {
            $consensus_forming = new ConsensusForming;
        }

        $consensus_forming->title = $request->title;
        $consensus_forming->description = $request->description;
        $consensus_forming->address = $request->address;
        $consensus_forming->latitude = $request->latitude;
        $consensus_forming->longitude = $request->longitude;
        $consensus_forming->audience = $request->audience;
        $consensus_forming->start_date = $request->start_date;
        $consensus_forming->end_date = $request->end_date;
        $consensus_forming->start_time = $request->start_time;
        $consensus_forming->end_time = $request->end_time;
        $consensus_forming->participation = $request->participation;
        $consensus_forming->vote_question = $request->vote_question;
        $consensus_forming->user_id = $request->user_id;
        if ($consensus_forming->save()) {
            if (!isset($request->id)) {
                foreach ($request->vote_option as $key => $vote_option) {
                    $option = new Option;
                    $option->parent_id = $consensus_forming->id;
                    $option->vote_option = $vote_option;
                    $option->vote_description = $request->vote_description[$key];
                    $option->save();
                }
            }
        }

        return response()->json($consensus_forming);
    }

    public function find($id)
    {
        $consensus_forming = ConsensusForming::with('comments', 'options')->withCount('comments', 'likes')->find($id);
        return response()->json($consensus_forming);
    }

    public function comment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'comment' => 'required',
            'parent_id' => 'required',
        ]);

        if ($validator->fails()) {
            $messages = $validator->messages()->all();

            return response()->json([
                'status' => 'Error',
                'message' => $messages[0],
            ], 200);
        }

        $comment = new Comment;
        $comment->user_id = $request->user_id;
        $comment->parent_id = $request->parent_id;
        $comment->comment = $request->comment;
        $comment->save();

        $comments = Comment::where('parent_id', $request->parent_id)->get();
        return response()->json($comments);
    }

    public function like(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'parent_id' => 'required',
        ]);

        if ($validator->fails()) {
            $messages = $validator->messages()->all();

            return response()->json([
                'status' => 'Error',
                'message' => $messages[0],
            ], 200);
        }

        $liked = Like::where(['user_id' => $request->user_id, 'parent_id' => $request->parent_id])->first();
        if (!is_null($liked)) {
            $liked->delete();
            $likes = Like::where(['parent_id' => $request->parent_id])->count();

            return response()->json($likes);
        }

        $like = new Like;
        $like->user_id = $request->user_id;
        $like->parent_id = $request->parent_id;
        $like->save();

        $likes = Like::where(['parent_id' => $request->parent_id])->count();

        return response()->json($likes);
    }

    public function user_option(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'parent_id' => 'required',
            'option_id' => 'required',
        ]);

        if ($validator->fails()) {
            $messages = $validator->messages()->all();

            return response()->json([
                'status' => 'Error',
                'message' => $messages[0],
            ], 200);
        }

        $user_option = new UserOption;
        $user_option->user_id = $request->user_id;
        $user_option->parent_id = $request->parent_id;
        $user_option->option_id = $request->option_id;
        $user_option->save();

        $option_array=array();
        $option=Option::where(['parent_id'=>$request->parent_id])->get();
        foreach($option as $item){
            $option_array[$item->id]=count(UserOption::where(['option_id'=>$item->id])->get());
        }

        return response()->json($option_array);
    }

    public function delete($id)
    {
        $consensus_forming = ConsensusForming::with('comments', 'options', 'likes', 'user_option')->find($id);
        if (!is_null($consensus_forming)) {
            $consensus_forming->comments()->delete();
            $consensus_forming->user_option()->delete();
            $consensus_forming->likes()->delete();
            $consensus_forming->options()->delete();
            $consensus_forming->delete();
        }
        return response()->json(['message' => 'Deleted']);
    }
}
