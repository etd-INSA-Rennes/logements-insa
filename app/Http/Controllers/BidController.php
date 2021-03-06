<?php

namespace App\Http\Controllers;

use Session;
use Storage;
use Mail;
use Illuminate\Http\Request;
use App\Http\Requests\BidRequest;
use App\Bid;
use App\User;
use App\Photo;

class BidController extends Controller
{
    public function __construct()
    {
        $this->middleware('owner', ['except' => ['index', 'store', 'create', 'show']]);
    }

    public function getResource($id)
    {
        return Bid::findOrFail($id);
    }

    public function index(Request $request)
    {
        $user_id = User::where('login', cas()->user())->first()->id;

        $bids = Bid::where('user_id', $user_id)->get();
        $bids->load('type');

        if ($request->session()->has('temp_folder_name'))
        {
            $request->session()->forget('temp_folder_name');
        }

        return view('bids.index', compact('bids'));
    }

    public function show($id)
    {
        $bid = Bid::findOrFail($id);

        if ($bid->isPending())
        {
            Session::flash('warning', 'Cette annonce n\'a pas encore été modérée et n\'est donc pas visible des autres utilisateurs.');
        }
        else if ($bid->isPostponed())
        {
            Session::flash('warning', 'Cette annonce a été mise en attente et n\'est donc pas visible des autres utilisateurs. Corrigez-là pour qu\'elle soit de nouveau modérée.');
        }

        return view('bids.show', compact('bid'));
    }

    public function create()
    {
        $bid = new Bid();

        return view('bids.create', compact('bid'));
    }

    private function storePhotos($bid, BidRequest $request)
    {
        if ($request->session()->has('temp_folder_name'))
        {
            $path = 'temp/' . $request->session()->get('temp_folder_name');

            $directories = Storage::disk('public')->directories($path);

            foreach($directories as $directory)
            {
                $files = Storage::disk('public')->files($directory);

                foreach($files as $file)
                {
                    $format = substr($directory, strrpos($directory, '/') + 1);
                    $filename = substr($file, strrpos($file, '/') + 1);

                    Photo::create([
                        'bid_id' => $bid->id,
                        'format' => $format,
                        'filename' => $filename
                    ]);

                    // Moving photos from temp directory

                    Storage::disk('public')->move($file, $bid->id . '/' . $format . '/' . $filename);
                }
            }

            $bid->photo_count = count(Storage::disk('public')->files($bid->id . '/original'));
            $bid->save();

            // Deleting temp directory

            Storage::disk('public')->deleteDirectory($path);

            // Flushing the Session

            $request->session()->forget('temp_folder_name');
        }
    }

    public function store(BidRequest $request)
    {
        $data = $request->all();
        $data['user_id'] = User::where('login', cas()->user())->first()->id;

        $bid = Bid::create($data);

        // Creating DB records for photos

        $this->storePhotos($bid, $request);

        // Sending a mail to moderators

        $emails = $this->adminEmails();

        Mail::send('emails.create', ['bid' => $bid, 'author' => cas()->user()], function($message) use ($emails)
        {
            $message->to($emails);
        });

        return redirect(route('bids.index'))->with('success', "Votre annonce a bien été créée. Elle est maintenant en cours de modération, et sera bientôt en ligne.");
    }

    public function edit($bid)
    {
        return view('bids.edit', compact('bid'));
    }

    public function update($bid, BidRequest $request)
    {
        $data = $request->all();
        $data['user_id'] = User::where('login', cas()->user())->first()->id;

        $bid->update($data);

        // Creating DB records for photos

        $this->storePhotos($bid, $request);

        if ($bid->isPostponed())
        {
            $bid->markPending();

            return redirect(route('bids.index'))->with('success', "Votre annonce a bien été mise à jour. Elle est maintenant en cours de modération, et sera bientôt en ligne.");
        }

        return redirect(route('bids.index'))->with('success', "Votre annonce a bien été mise à jour.");
    }

    public function destroy($bid)
    {
        $bid->delete();

        return redirect(route('bids.index'))->with('success', "Votre annonce a bien été supprimée.");
    }

    private function adminEmails()
    {
        $admins = User::admin()->get();
        $res = [];

        foreach ($admins as $admin)
        {
            $res[] = $admin->email();
        }

        return $res;
    }
}
