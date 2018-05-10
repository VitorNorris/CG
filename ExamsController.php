<?php

namespace App\Http\Controllers\Site;

use App\Repositories\CategoryExamRepository;
use App\Repositories\CertificateRepository;
use App\Repositories\ExamsRepository;
use App\Repositories\ExamUnidsRepository;
use App\Repositories\PageContentTypeRepository;
use App\Repositories\PageRepository;
use App\Repositories\RegionsRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\SpecialtyRepository;
use App\Services\SiteService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;


class ExamsController extends Controller
{
    private $exams;
    private $region;
    private $page;
    private $page_content_type;
    private $category_exam;
    private $exam_unid;
    private $settings;
    private $specialties;
    private $certificate;

    public function __construct(ExamsRepository $exams, RegionsRepository $region, PageRepository $page, PageContentTypeRepository $page_content_type, CategoryExamRepository $category_exam, ExamUnidsRepository $exam_unid, SettingsRepository $settings, SpecialtyRepository $specialties, CertificateRepository $certificate)
    {
        $this->exams = $exams;
        $this->region = $region;
        $this->page = $page;
        $this->page_content_type = $page_content_type;
        $this->category_exam = $category_exam;
        $this->exam_unid = $exam_unid;
        $this->settings = $settings;
        $this->specialties = $specialties;
        $this->certificate = $certificate;
    }

    public function index(Request $request, $page = 'exames', $slug_name = null)
    {
        $name = $page;

        $title = config('app.name');
        $description = null;
        $keywords = null;
        $author = null;

        if (str_contains($page, '#')) $name = explode('#', $page)[0];

        $url = '/';

        //dd($page);

        if ($page != 'index') $url .= $name;


        $page_data = $this->page->where(['url' => $url])->first();

        if (!is_null($page_data) && !$page_data->publish) abort(404, 'Página não publicada na Administração');

        if (!is_null($page_data)) {
            $title = $page_data->title;
            $description = $page_data->description != '' ? $page_data->description : null;
            $keywords = $page_data->keywords != '' ? $page_data->keywords : null;
            $author = $page_data->author != '' ? $page_data->author : null;
        }

        $seo = array('title' => $title, 'description' => $description, 'keywords' => $keywords, 'author' => $author);

        if (!file_exists(resource_path("views/site/pages/exames/{$name}.blade.php"))) abort(404);

        $page = $page_data;

        $page_content_types = '';


        if ($page->dynamization) { //verificando se a página é uma pagina dinamizada
            $page_content_types = $this->page_content_type->with('type.content')->whereHas('page', function ($query) use ($page) {
                $query->where('page_id', '=', $page->id);
            })->orderBy('order', 'asc')->all();
        }

        if ($name == 'agendar') {
            //$category_id = 2;
            $exams = $this->exams->orderBy('name', 'asc')->where(['most_wanted' => 1, 'category_id' => 2])->paginate(10);

        } else if ($name == 'setor-feminino') {
            //$category_id = 1;
            $exams = $this->exams->orderBy('name', 'asc')->where(['most_wanted' => 1, 'category_id' => 1])->get();
        }else {
            //$category_id = null;
        }


        if (!is_null($slug_name)) {
            $exam_array = $this->exams->with('specialty')->where(['slug_name' => $slug_name])->first();
            if (!is_null($exam_array)) {
                $exam = $this->exams->with('specialty')->where(['slug_name' => $slug_name])->first();
                $unids_exam = $this->exam_unid->with('unids.regions')->where(['exam_id' => $exam->id])->get();
            } else {
                return redirect()->route('index');
            }
        }

        $specialties = $this->specialties->orderBy('order', 'asc')->where(['display_specialty_medical' => 1])->get();

        $regions = $this->region->with('unids')->orderBy('name', 'desc')->all();
        $settings = $this->settings->first();
        $certificates = $this->certificate->where(['status' => 1])->get();
        $folder = $name;




        //Chamada Web Service - Metodo para recuperar todos os exames (COM CACHE)
        $expiration_date = now()->addDays(1);
        $exames = Cache::remember('exames', $expiration_date, function () {
            $service = new SiteService();
            $result = $service->request('WsGetTodosExames', array('pConfiguracaoWeb' => '?'));
            $exames = json_decode(json_encode($result->listaProcedimento), true);
            return $exames;
        });


        $exames = collect($exames['procedimentoSimples'])->filter(function ($exame) {
            $exame['menmonico'] = is_array($exame['menmonico']) ? implode('', $exame['menmonico']) : $exame['menmonico'];
            $exame['nome'] = is_array($exame['nome']) ? implode('', $exame['nome']) : $exame['nome'];
            return strlen(trim($exame['nome'])) > 2 || strlen(trim($exame['menmonico'])) > 2;

        });

        $exames = paginate($exames, 20);
        //FIm
        $folder = 'exames';
        return view("site.pages.exames.{$name}", compact('folder', 'seo', 'exams', 'exam', 'regions', 'page_content_types', 'seo', 'unids_exam', 'name', 'settings', 'specialties', 'certificates', 'exames'));
    }

    public function search(Request $request, $page = null)
    {
        $letter = strtolower($request->input('letter'));
        $action = $request->input('action');


        if ($page == 'agendar') {
            $category_id = 2;
        } else if ($page == 'setor-feminino') {
            $category_id = 1;
        } else {
            $category_id = null;
        }

        if ($action == 'letter') {
            $letter = $letter . '%';
        } else if ($action == 'word') {
            $letter = str_replace(' ', '%', $letter) . '%';
        }

        if (!is_null($category_id) && $page != 'especialidades-medicas') {
            if (!is_null($letter)) {
                $exams = $this->exams->where([['name', 'LIKE', "{$letter}"], 'most_wanted' => 1, 'category_id' => $category_id])->get();
                return response()->json($exams);
            }
        }

        if ($page == 'especialidades-medicas') {
            $specialties = $this->specialties->where([['name', 'LIKE', "{$letter}"]])->get();
            return response()->json($specialties);
        }

    }

    public function detals(Request $request, $id, $page = 'exames', $slug_name = null)
    {
        //
        $name = $page;

        $title = config('app.name');
        $description = null;
        $keywords = null;
        $author = null;

        if (str_contains($page, '#')) $name = explode('#', $page)[0];

        $url = '/';



        if ($page != 'index') $url .= $name;


        $page_data = $this->page->where(['url' => $url])->first();

        if (!is_null($page_data) && !$page_data->publish) abort(404, 'Página não publicada na Administração');

        if (!is_null($page_data)) {
            $title = $page_data->title;
            $description = $page_data->description != '' ? $page_data->description : null;
            $keywords = $page_data->keywords != '' ? $page_data->keywords : null;
            $author = $page_data->author != '' ? $page_data->author : null;
        }

        $seo = array('title' => $title, 'description' => $description, 'keywords' => $keywords, 'author' => $author);

        //dd($name);
        if (!file_exists(resource_path("views/site/pages/exames/{$name}.blade.php"))) abort(404);

        $page = $page_data;

        $page_content_types = '';


        if ($page->dynamization) { //verificando se a página é uma pagina dinamizada
            $page_content_types = $this->page_content_type->with('type.content')->whereHas('page', function ($query) use ($page) {
                $query->where('page_id', '=', $page->id);
            })->orderBy('order', 'asc')->all();
        }



        if ($name == 'agendar') {

            $exams = $this->exams->orderBy('name', 'asc')->where(['most_wanted' => 1, 'category_id' => 2])->paginate(10);
        } else if ($name == 'setor-feminino') {

            $exams = $this->exams->orderBy('name', 'asc')->where(['most_wanted' => 1, 'category_id' => 1])->get();
        } else {
            //$category_id = null;
        }



        if (!is_null($slug_name)) {
            $exam_array = $this->exams->with('specialty')->where(['slug_name' => $slug_name])->first();
            if (!is_null($exam_array)) {
                $exam = $this->exams->with('specialty')->where(['slug_name' => $slug_name])->first();
                $unids_exam = $this->exam_unid->with('unids.regions')->where(['exam_id' => $exam->id])->get();
            } else {
                return redirect()->route('index');
            }
        }

        $specialties = $this->specialties->orderBy('order', 'asc')->where(['display_specialty_medical' => 1])->get();

        $regions = $this->region->with('unids')->orderBy('name', 'desc')->all();
        $settings = $this->settings->first();
        $certificates = $this->certificate->where(['status' => 1])->get();
        $folder = $name;

        //
        $service = new SiteService();
        $result = $service->request('WsGetExamesById', array('pExameId' => $id, 'pConfiguracaoWeb' => '', 'pSiglaURL' => ''));
        $exames = json_decode(json_encode($result), true);


        $unidades = $this->exams->with(['exames_unidades' => function ($query) {
            $query->with(['unids'=> function ($query){
                $query->with(['regions']);
            }]);
        }])->where(['name' => $exames['nome']])->get()->filter(function ($exame) {
            return count($exame['exames_unidades']) > 0;
        })->toArray();

        return view("site.pages.exames.nome-exame", compact('folder', 'seo', 'exams', 'exam', 'regions', 'page_content_types', 'seo', 'unids_exam', 'name', 'settings', 'specialties', 'certificates', 'exames','unidades'));
        //FIm
    }


    public function search2(Request $request, $letra, $page = 'exames', $slug_name = null)
    {
        $name = $page;


        $title = config('app.name');
        $description = null;
        $keywords = null;
        $author = null;

        if (str_contains($page, '#')) $name = explode('#', $page)[0];

        $url = '/';

        //dd($page);

        if ($page != 'index') $url .= $name;


        $page_data = $this->page->where(['url' => $url])->first();

        if (!is_null($page_data) && !$page_data->publish) abort(404, 'Página não publicada na Administração');

        if (!is_null($page_data)) {
            $title = $page_data->title;
            $description = $page_data->description != '' ? $page_data->description : null;
            $keywords = $page_data->keywords != '' ? $page_data->keywords : null;
            $author = $page_data->author != '' ? $page_data->author : null;
        }

        $seo = array('title' => $title, 'description' => $description, 'keywords' => $keywords, 'author' => $author);


        if (!file_exists(resource_path("views/site/pages/exames/{$name}.blade.php"))) abort(404);

        $page = $page_data;

        $page_content_types = '';


        if ($page->dynamization) { //verificando se a página é uma pagina dinamizada
            $page_content_types = $this->page_content_type->with('type.content')->whereHas('page', function ($query) use ($page) {
                $query->where('page_id', '=', $page->id);
            })->orderBy('order', 'asc')->all();
        }

        if ($name == 'agendar') {
            //$category_id = 2;
            $exams = $this->exams->orderBy('name', 'asc')->where(['most_wanted' => 1, 'category_id' => 2])->paginate(10);
        } else if ($name == 'setor-feminino') {
            //$category_id = 1;
            $exams = $this->exams->orderBy('name', 'asc')->where(['most_wanted' => 1, 'category_id' => 1])->get();
        } else {
            //$category_id = null;
        }



        if (!is_null($slug_name)) {
            $exam_array = $this->exams->with('specialty')->where(['slug_name' => $slug_name])->first();
            if (!is_null($exam_array)) {
                $exam = $this->exams->with('specialty')->where(['slug_name' => $slug_name])->first();
                $unids_exam = $this->exam_unid->with('unids.regions')->where(['exam_id' => $exam->id])->get();
            } else {
                return redirect()->route('index');
            }
        }

        $specialties = $this->specialties->orderBy('order', 'asc')->where(['display_specialty_medical' => 1])->get();

        $regions = $this->region->with('unids')->orderBy('name', 'desc')->all();
        $settings = $this->settings->first();
        $certificates = $this->certificate->where(['status' => 1])->get();
        $folder = $name;



        //Se for apenas uma letra
        if (strlen($letra) == 1) {


            //Chamada Web Service  Metodo para recuperar todos os exames
            //COM CACHE
            $expiration_date = now()->addDays(1);
            $exames2 = Cache::remember('exames-search', $expiration_date, function () {
                $service = new SiteService();
                $result = $service->request('WsGetTodosExames', array('pConfiguracaoWeb' => '?'));
                $exames1 = json_decode(json_encode($result->listaProcedimento), true);
                $exames2 = $exames1['procedimentoSimples'];
                return $exames2;
            });
            //

            $exames = array();

            foreach ($exames2 as $exame) {

                if (isset($exame['nome'][0])) {
                    if (($exame['nome'][0] == strtoupper($letra)) && (is_string($exame['nome']))) {
                        array_push($exames, $exame);
                    }
                }

            }

            //
            $exames = collect($exames)->filter(function ($exame) {
                $exame['menmonico'] = is_array($exame['menmonico']) ? implode('', $exame['menmonico']) : $exame['menmonico'];
                $exame['nome'] = is_array($exame['nome']) ? implode('', $exame['nome']) : $exame['nome'];
                return strlen(trim($exame['nome'])) > 2 || strlen(trim($exame['menmonico'])) > 2;

            });
            //

            $exames = json_decode(json_encode($exames), true);
            $exames = paginate($exames, 20);
        } else {
            return back();
        }
        $page_active='exames e preparo';

        return view("site.pages.exames.{$name}", compact('folder', 'seo', 'exams', 'exam', 'regions', 'page_content_types', 'seo', 'unids_exam', 'name', 'settings', 'specialties', 'certificates', 'exames','page_active'));


    }

    public function search3(Request $request, $page = 'exames', $slug_name = null)
    {


        $palavra = $request['exame'];

        $name = $page;

        $title = config('app.name');
        $description = null;
        $keywords = null;
        $author = null;

        if (str_contains($page, '#')) $name = explode('#', $page)[0];

        $url = '/';



        if ($page != 'index') $url .= $name;


        $page_data = $this->page->where(['url' => $url])->first();

        if (!is_null($page_data) && !$page_data->publish) abort(404, 'Página não publicada na Administração');

        if (!is_null($page_data)) {
            $title = $page_data->title;
            $description = $page_data->description != '' ? $page_data->description : null;
            $keywords = $page_data->keywords != '' ? $page_data->keywords : null;
            $author = $page_data->author != '' ? $page_data->author : null;
        }

        $seo = array('title' => $title, 'description' => $description, 'keywords' => $keywords, 'author' => $author);

        //dd($name);
        if (!file_exists(resource_path("views/site/pages/exames/{$name}.blade.php"))) abort(404);

        $page = $page_data;

        $page_content_types = '';


        if ($page->dynamization) { //verificando se a página é uma pagina dinamizada
            $page_content_types = $this->page_content_type->with('type.content')->whereHas('page', function ($query) use ($page) {
                $query->where('page_id', '=', $page->id);
            })->orderBy('order', 'asc')->all();
        }

        if ($name == 'agendar') {
            //$category_id = 2;
            $exams = $this->exams->orderBy('name', 'asc')->where(['most_wanted' => 1, 'category_id' => 2])->paginate(10);
        } else if ($name == 'setor-feminino') {
            //$category_id = 1;
            $exams = $this->exams->orderBy('name', 'asc')->where(['most_wanted' => 1, 'category_id' => 1])->get();
        } else {
            //$category_id = null;
        }


        if (!is_null($slug_name)) {
            $exam_array = $this->exams->with('specialty')->where(['slug_name' => $slug_name])->first();
            if (!is_null($exam_array)) {
                $exam = $this->exams->with('specialty')->where(['slug_name' => $slug_name])->first();
                $unids_exam = $this->exam_unid->with('unids.regions')->where(['exam_id' => $exam->id])->get();
            } else {
                return redirect()->route('index');
            }
        }

        $specialties = $this->specialties->orderBy('order', 'asc')->where(['display_specialty_medical' => 1])->get();

        $regions = $this->region->with('unids')->orderBy('name', 'desc')->all();
        $settings = $this->settings->first();
        $certificates = $this->certificate->where(['status' => 1])->get();
        $folder = $name;


        //Se for apenas uma letra
        if (strlen($palavra) >= 1) {
            $expiration_date = now()->addDays(1);
            $exames2 = Cache::remember('exames-search-word', $expiration_date, function () {
                $service = new SiteService();
                $result = $service->request('WsGetTodosExames', array('pConfiguracaoWeb' => '?'));
                $exames1 = json_decode(json_encode($result->listaProcedimento), true);
                $exames2 = $exames1['procedimentoSimples'];
                return $exames2;
            });


            $exames = array();


            $exames = collect($exames2)->filter(function ($exame) use ($palavra) {
                $exame['menmonico'] = is_array($exame['menmonico']) ? implode('', $exame['menmonico']) : $exame['menmonico'];
                $exame['nome'] = is_array($exame['nome']) ? implode('', $exame['nome']) : $exame['nome'];

                return str_contains(strtoupper($exame['menmonico']), strtoupper($palavra)) || str_contains(strtoupper($exame['nome']), strtoupper($palavra));
            });

            $exames = json_decode(json_encode($exames), true);
            $exames = paginate($exames, 20);

            $exames->withPath('?exame=' . $request->exame);

        } else {

            return redirect()->back();
        }

        $page_active='exames e preparo';
        return view("site.pages.exames.{$name}", compact('folder', 'seo', 'exams', 'exam', 'regions', 'page_content_types', 'seo', 'unids_exam', 'name', 'settings', 'specialties', 'certificates', 'exames','page_active'));


    }


}
