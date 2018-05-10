<?php

namespace App\Http\Controllers;

use App\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index()
    {
        $banner = Banner::find(1);

        return view('banner_promocional', compact('banner'));
    }

    public function edit()
    {
        $banner = Banner::find(1);
        return view('banner_edit', compact('banner'));
    }

    public function update(Request $request)
    {
        $data = $request->all();
        if ($request->has('disable')) {

            if(empty($data['disable'])){
                $data['disable'] = 0;
            }

            $banner = Banner::find(1);
            $banner->fill($data);
            $banner->save();

            return response()->json(true);

        }
        if ($request->hasFile('images')) {
            $file = $request->file('images');

            $filename = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $picture = date('His') . $filename;
            $destinationPath = public_path('/storage/banner-promocional');
            $data['path_file'] = '/storage/banner-promocional/' . $picture;
            $file->move($destinationPath, $picture);

            $banner = Banner::find(1);
            $banner->fill($data);
            $banner->save();
            flash()->success('Banner alterado com suscesso!');
            return redirect()->back();


        } else {
            $banner = Banner::find(1);

            $data['path_file'] = $banner['path_file'];
            $banner->fill($data);
            $banner->save();
            flash()->success('Banner alterado com sucesso!');
            return redirect()->back();

        }


    }

    public function active()
    {
        return view('faq');
    }

}
