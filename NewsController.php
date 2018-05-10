<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\NewsRepository;
use App\Http\Requests\Admin\NewsRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\UploadedFile;

class NewsController extends Controller
{
    private $news;

    public function __construct(NewsRepository $news)
    {
        $this->news = $news;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $news = $this->news->paginate();

        return view('admin.news.index', compact('news'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.news.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(NewsRequest $request)
    {
        $attributes = $request->all();
        $attributes['slug'] = $attributes['seo_slug'] ?: str_slug($attributes['title']);
        $attributes['created_at'] = Carbon::createFromFormat('d/m/Y', $attributes['created_at'])->toDateTimeString();

        foreach ($attributes as $name => $input) {
            if ($input instanceof UploadedFile) {
                $attributes[$name] = "storage/{$this->storage()->putFileAs('', $input, $input->getClientOriginalName())}";
            }
        }

        $attributes['seo_title'] = $attributes['seo_title'] ?: $attributes['title'];
        $attributes['seo_slug'] = $attributes['slug'];

        $news = $this->news->create($attributes);
        $news->seo()->create($attributes);

        return redirect()->route('admin.news.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $news = $this->news->with('seo')->find($id);

        return view('admin.news.edit', compact('news'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(NewsRequest $request, $id)
    {
        $attributes = $request->all();

        if(!$request->expectsJson()){
            $attributes['slug'] = $attributes['seo_slug'] ?: str_slug($attributes['title']);
            $attributes['created_at'] = Carbon::createFromFormat('d/m/Y', $attributes['created_at'])->toDateTimeString();
        }

        foreach ($attributes as $name => $input) {
            if ($input instanceof UploadedFile) {
                $attributes[$name] = "storage/{$this->storage()->putFileAs('', $input, $input->getClientOriginalName())}";
            }
        }

        $news = $this->news->update($attributes, $id);

        if ($request->expectsJson()) return response()->json($news);

        $attributes['seo_title'] = $attributes['seo_title'] ?: $attributes['title'];

        $seo = $news->seo;
        $seo->fill($attributes);
        $seo->save();

        return redirect()->route('admin.news.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        return response()->json($this->news->delete($id));
    }
}
