<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Response;

use App\Bid;
use App\Photo;
use Intervention\Image\ImageManagerStatic as Image;
use Storage;
use File;

class UploadController extends Controller
{
    public $formats = [
        'thumb' => [360, 200],
        'large' => [940, 530]
    ];

    /*
    *
    */
    public function upload(Request $request)
    {
        $input = Input::all();

        $validator = Validator::make($input, Photo::$rules);

        if ($validator->fails())
        {
            return Response::json([
                'error' => true,
                'message' => $validator->messages()->first(),
                'code' => 400
            ], 400);
        }

        $photo = $input['file'];

        // Storing the temp folder name in the Session

        $temp_folder_name = $request->session()->get('temp_folder_name', str_random(32));

        if (!$request->session()->has('temp_folder_name'))
        {
            $request->session()->put('temp_folder_name', $temp_folder_name);
        }

        // Generating a filename

        $filename = str_random(32) . '.' . $photo->getClientOriginalExtension();

        foreach ($this->formats as $format => $dimensions)
        {
            Storage::disk('public')->put('temp/' . $temp_folder_name . '/' . $format . '/' . $filename, Image::make($photo)->fit($dimensions[0], $dimensions[1])->stream()->__toString());
        }

        Storage::disk('public')->put('temp/' . $temp_folder_name . '/original/' . $filename, Image::make($photo)->stream()->__toString());
    }

    /*
    *
    */
    public function delete(Request $request)
    {
        $bid_id = Input::get('id');
        $filename = Input::get('filename');

        if (!Bid::where('id', $bid_id)->exists())
        {
            if ($request->session()->has('temp_folder_name'))
            {
                $path = 'temp/' . $request->session()->get('temp_folder_name');

                $directories = Storage::disk('public')->directories($path);

                foreach ($directories as $directory)
                {
                    $files = Storage::disk('public')->files($directory);

                    foreach ($files as $file)
                    {
                        if (strcmp(substr($file, strrpos($file, '/') + 1), $filename) == 0)
                        {
                            Storage::disk('public')->delete($file);
                        }
                    }
                }
            }
        }
        else
        {
            $directories = Storage::disk('public')->directories($bid_id);

            foreach ($directories as $directory)
            {
                $files = Storage::disk('public')->files($directory);

                foreach ($files as $file)
                {
                    if (strcmp(substr($file, strrpos($file, '/') + 1), $filename) == 0)
                    {
                        Photo::where('filename', $filename)->where('bid_id', $bid_id)->where('format', substr($directory, strrpos($directory, '/') + 1))->delete();

                        Storage::disk('public')->delete($file);
                    }
                }
            }

            $bid = Bid::findOrFail($bid_id);

            $bid->photo_count = count(Storage::disk('public')->files($bid_id . '/original'));
            $bid->save();
        }
    }

    /*
    *
    */
    public function getPhotos(Request $request, $bid_id = null)
    {
        $res = [];

        if (!Bid::where('id', $bid_id)->exists())
        {
            if ($request->session()->has('temp_folder_name'))
            {
                $path = 'temp/' . $request->session()->get('temp_folder_name');

                $directories = Storage::disk('public')->directories($path);

                foreach ($directories as $directory)
                {
                    if (strcmp(substr($directory, strrpos($directory, '/') + 1), 'original') == 0)
                    {
                        $files = Storage::disk('public')->files($directory);

                        foreach ($files as $file)
                        {
                            $filename = substr($file, strrpos($file, '/') + 1);

                            $res[] = [
                                'filename' => $filename,
                                'size' => Storage::disk('public')->size($path . '/original/' . $filename),
                                'server' => url(Storage::disk('public')->url($path . '/thumb/' . $filename))
                            ];
                        }
                    }
                }
            }
        }
        else
        {
            $photos = Photo::where('bid_id', $bid_id)->where('format', 'thumb')->get();

            for ($i = 1; $i <= Bid::where('id', $bid_id)->first()->photo_count; $i++)
            {
                $photo = $photos[$i - 1];

                $filename = $photo->filename;

                $res[] = [
                    'filename' => $filename,
                    'size' => Storage::disk('public')->size($bid_id . '/original/' . $filename),
                    'server' => url(Storage::disk('public')->url($bid_id . '/thumb/' . $filename))
                ];
            }
        }

        return response()->json([
            'photos' => $res
        ]);
    }
}
