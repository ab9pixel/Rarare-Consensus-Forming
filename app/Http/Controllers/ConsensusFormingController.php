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

    public function list( $count, $user_id, $type )
    {

        if ( $count != 0 ) {
            if ( $type == "l") {
                if ( $user_id != 0) {

                    $consensus_forming = ConsensusForming::withCount( 'comments', 'likes' )->with( 'comments', 'options' )->where( 'user_id', $user_id )->orderBy( 'status', 'desc' )->orderBy( 'created_at', 'desc' )->limit( $count )->get();
                }else {
                    $consensus_forming = ConsensusForming::withCount('comments', 'likes')->with('comments', 'options')->where('status', '1')->orderBy('status', 'desc')->orderBy('created_at', 'desc')->limit($count)->get();
                }
                } else {
                    $consensus_forming = ConsensusForming::withCount( 'comments', 'likes' )->with( 'comments', 'options' )->where( 'user_id', $user_id )->orderBy( 'status', 'desc' )->orderBy( 'created_at', 'desc' )->limit( $count )->get();
            }
        } else {
            if ( $type == "l" ) {
                $consensus_forming = ConsensusForming::withCount( 'comments', 'likes' )->with( 'comments', 'options' )->where( 'status', '1' )->orderBy( 'status', 'desc' )->orderBy( 'created_at', 'desc' )->limit('10')->get();

            } else {
                $consensus_forming = ConsensusForming::withCount( 'comments', 'likes' )->with( 'comments', 'options' )->where( 'user_id', $user_id )->orderBy( 'status', 'desc' )->orderBy( 'created_at', 'desc' )->get();
            }
        }

        if ( $consensus_forming->isEmpty() ) {
            $data = [];
            return response()->json($data);
        }
        if ( $type == "l" && $user_id != 0) {

            $user = $this->get_user( $user_id );

            if ( ! is_null( $user->latitude ) ) {


                foreach ( $consensus_forming as $key => $forming ) {

                    $source = [
                        'lat' => $forming->latitude,
                        'lng' => $forming->longitude
                    ];

                    $destination = [
                        'lat' => $user->latitude,
                        'lng' => $user->longitude
                    ];

                    $mile = $this->calculate_distance( $source, $destination );

                    if ( $mile > 30 ) {
                        $consensus_forming->forget( $key );
                        $data = $consensus_forming;
                    } else {
                        $data[] = $forming;
                    }
                }

            }

        } else {

            $data = $consensus_forming;
        }


            return response()->json($data);

    }

    public function search( Request $request )
    {
        $title = $request->title;
        $lat = $request->latitude;
        $lng = $request->longitude;


            $consensus_forming = ConsensusForming::where('title', 'like', '%' . $title . '%')->orderBy( 'status', 'desc' )->orderBy( 'created_at', 'desc' )->get();

//            dd($consensus_forming[items]);
            if ( ! $consensus_forming->isEmpty() ) {


                foreach ( $consensus_forming as $key => $forming ) {

                    $source = [
                        'lat' => $forming->latitude,
                        'lng' => $forming->longitude
                    ];

                    $destination = [
                        'lat' => $lat,
                        'lng' => $lng
                    ];

                    $mile = $this->calculate_distance( $source, $destination );

                    if ( $mile > 30 ) {
                        $consensus_forming->forget( $key );
                        $data = []  ;
                    } else {
                        $data[] = $forming;
                    }
                }

            }else{
                $data = []  ;
            }



        return response()->json($data);

    }

    public function save( Request $request )
    {
        $validator = Validator::make( $request->all(), [
            'title'         => 'required|max:255',
            'description'   => 'required',
            'address'       => 'required',
            'latitude'      => 'required',
            'longitude'     => 'required',
            'audience'      => 'required',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date',
            'start_time'    => 'required|date_format:H:i',
            'end_time'      => 'required|date_format:H:i|after:start_time',
            'user_id'       => 'required',
            'participation' => 'required',
            'vote_question' => 'required',
        ] );
        if ( $validator->fails() ) {
            $messages = $validator->messages()->all();

            return response()->json( [
                                         'status'  => 'Error',
                                         'message' => $messages[0],
                                     ], 200 );
        }

        if ( isset( $request->id ) ) {
            $consensus_forming = ConsensusForming::find( $request->id );
        } else {
            $consensus_forming = new ConsensusForming;
        }

        $consensus_forming->title         = $request->title;
        $consensus_forming->description   = $request->description;
        $consensus_forming->address       = $request->address;
        $consensus_forming->latitude      = $request->latitude;
        $consensus_forming->longitude     = $request->longitude;
        $consensus_forming->audience      = $request->audience;
        $consensus_forming->start_date    = $request->start_date;
        $consensus_forming->end_date      = $request->end_date;
        $consensus_forming->start_time    = $request->start_time;
        $consensus_forming->end_time      = $request->end_time;
        $consensus_forming->participation = $request->participation;
        $consensus_forming->vote_question = $request->vote_question;
        $consensus_forming->timezone      = $request->timezone;
        $consensus_forming->user_id       = $request->user_id;
        if ( $consensus_forming->save() ) {
            if ( ! isset( $request->id ) ) {
                foreach ( $request->vote_option as $key => $vote_option ) {
                    $option                   = new Option;
                    $option->parent_id        = $consensus_forming->id;
                    $option->vote_option      = $vote_option;
                    $option->vote_description = $request->vote_description[ $key ];
                    $option->save();
                }
            }
        }

        return response()->json( $consensus_forming );
    }

    public function find( $id )
    {
        $consensus_forming = ConsensusForming::with( 'comments', 'options' )->withCount( 'comments', 'likes' )->find( $id );

        return response()->json( $consensus_forming );
    }

    public function comment( Request $request )
    {
        $validator = Validator::make( $request->all(), [
            'user_id'   => 'required',
            'comment'   => 'required',
            'parent_id' => 'required',
        ] );

        if ( $validator->fails() ) {
            $messages = $validator->messages()->all();

            return response()->json( [
                                         'status'  => 'Error',
                                         'message' => $messages[0],
                                     ], 200 );
        }

        $comment            = new Comment;
        $comment->user_id   = $request->user_id;
        $comment->parent_id = $request->parent_id;
        $comment->comment   = $request->comment;
        $comment->save();

        $comments          = Comment::where( 'parent_id', $request->parent_id )->get();
        $consensus_forming = ConsensusForming::find( $request->parent_id );

        if ( $consensus_forming->participation == 1 ) {
            $post['user_id']       = $consensus_forming->user_id;
            $post['object_id']     = $consensus_forming->id;
            $post['action']        = "Commented";
            $post['type']          = "Consensus Forming";
            $post['vote_question'] = $consensus_forming->vote_question;
            $post['message']       = $consensus_forming->description;
            $post['url']           = "https://staging.rarare.com/proposal?id=" . $request->parent_id;
            $post['title']         = $consensus_forming->title;
            $post['sender_id']     = $request->user_id;
            $this->send_notification( $post );
        }

        return response()->json( $comments );
    }

    public function like( Request $request )
    {
        $validator = Validator::make( $request->all(), [
            'user_id'   => 'required',
            'parent_id' => 'required',
        ] );

        if ( $validator->fails() ) {
            $messages = $validator->messages()->all();

            return response()->json( [
                                         'status'  => 'Error',
                                         'message' => $messages[0],
                                     ], 200 );
        }

        $liked = Like::where( [ 'user_id' => $request->user_id, 'parent_id' => $request->parent_id ] )->first();
        if ( ! is_null( $liked ) ) {
            $liked->delete();
            $likes = Like::where( [ 'parent_id' => $request->parent_id ] )->count();

            return response()->json( $likes );
        }

        $like            = new Like;
        $like->user_id   = $request->user_id;
        $like->parent_id = $request->parent_id;
        $like->save();

        $likes             = Like::where( [ 'parent_id' => $request->parent_id ] )->count();
        $consensus_forming = ConsensusForming::find( $request->parent_id );

        if ( $consensus_forming->participation == 1 ) {
            $post['user_id']       = $consensus_forming->user_id;
            $post['object_id']     = $consensus_forming->id;
            $post['action']        = "Liked";
            $post['type']          = "Consensus Forming";
            $post['vote_question'] = $consensus_forming->vote_question;
            $post['message']       = $consensus_forming->description;
            $post['url']           = "https://staging.rarare.com/proposal?id=" . $request->parent_id;
            $post['title']         = $consensus_forming->title;
            $post['sender_id']     = $request->user_id;
            $this->send_notification( $post );
        }

        return response()->json( $likes );
    }

    public function user_option( Request $request )
    {
        $validator = Validator::make( $request->all(), [
            'user_id'   => 'required',
            'parent_id' => 'required',
            'option_id' => 'required',
        ] );

        if ( $validator->fails() ) {
            $messages = $validator->messages()->all();

            return response()->json( [
                                         'status'  => 'Error',
                                         'message' => $messages[0],
                                     ], 200 );
        }

        $user_option            = new UserOption;
        $user_option->user_id   = $request->user_id;
        $user_option->parent_id = $request->parent_id;
        $user_option->option_id = $request->option_id;
        $user_option->save();

        $consensus_forming = ConsensusForming::find( $request->parent_id );

        $option_array = array();
        $option       = Option::where( [ 'parent_id' => $request->parent_id ] )->get();

        if ( $consensus_forming->audience <= count( $option ) ) {
            $consensus_forming->status = 1;
            $consensus_forming->save();
        }

        foreach ( $option as $item ) {
            $option_array[ $item->id ] = count( UserOption::where( [ 'option_id' => $item->id ] )->get() );
        }

        $consensus_forming = ConsensusForming::find( $request->parent_id );
        if ( $consensus_forming->participation == 1 ) {
            $post['user_id']       = $consensus_forming->user_id;
            $post['object_id']     = $consensus_forming->id;
            $post['action']        = "Voted";
            $post['type']          = "Consensus Forming";
            $post['vote_question'] = $consensus_forming->vote_question;
            $post['message']       = $consensus_forming->description;
            $post['url']           = "https://staging.rarare.com/proposal?id=" . $request->parent_id;
            $post['title']         = $consensus_forming->title;
            $post['sender_id']     = $request->user_id;
            $this->send_notification( $post );
        }

        return response()->json( $option_array );
    }

    public function delete( $id )
    {
        $consensus_forming = ConsensusForming::with( 'comments', 'options', 'likes', 'user_option' )->find( $id );
        if ( ! is_null( $consensus_forming ) ) {
            $consensus_forming->comments()->delete();
            $consensus_forming->user_option()->delete();
            $consensus_forming->likes()->delete();
            $consensus_forming->options()->delete();
            $consensus_forming->delete();
        }

        return response()->json( [ 'message' => 'Deleted' ] );
    }

    public function send_notification( $post )
    {
        $curl = curl_init();

        curl_setopt_array( $curl, array(
            CURLOPT_URL            => 'https://rrci.staging.rarare.com/proposal/subscribe/email',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => array(
                'title'         => $post['title'],
                'type'          => $post['type'],
                'vote_question' => $post['vote_question'],
                'message'       => $post['message'],
                'action'        => $post['action'],
                'url'           => $post['url'],
                'user_id'       => $post['user_id'],
                'object_id'     => $post['object_id'],
                'sender_id'     => $post['sender_id']
            ),
        ) );

        $response = curl_exec( $curl );

        curl_close( $curl );

        return true;
    }

    public function get_user( $id )
    {
        $curl = curl_init();

        curl_setopt_array( $curl, array(
            CURLOPT_URL            => 'https://rrci.staging.rarare.com/user/' . $id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
        ) );

        $response = curl_exec( $curl );

        curl_close( $curl );

        return json_decode( $response );
    }

    function calculate_distance( $source, $destination )
    {
        $lat1  = floatval( $source['lat'] );
        $lon1  = floatval( $source['lng'] );
        $lat2  = floatval( $destination['lat'] );
        $lon2  = floatval( $destination['lng'] );
        $theta = $lon1 - $lon2;
        $dist  = sin( deg2rad( $lat1 ) ) * sin( deg2rad( $lat2 ) ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * cos( deg2rad( $theta ) );
        $dist  = acos( $dist );
        $dist  = rad2deg( $dist );
        $miles = $dist * 60 * 1.1515;

        return $miles;
    }
}
