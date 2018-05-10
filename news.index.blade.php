@extends('admin.layouts.app')

@section('content')
    <div class="title flex-space no-flex-sm">
        <h4>Notícias</h4>
        <div class="box-btn-top">
            <a href="{{ route('admin.news.create') }}" class="btn btn-small icon"><i class="fa fa-plus"></i>Novo</a>
            <a href="#!" class="btn btn-small icon btn-destroy-all" data-target=".table-news"><i class="fa fa-trash"></i>Excluir
                selecionados</a>
        </div>
    </div>

    <!-- table -->
    <table class="tabela-padrao check-custom table-news toggle-destroy-parent">
        <thead>
        <tr>
            <th class="col-xs-2 col-lg-1 text-center">&nbsp;</th>
            <th class="col-xs-2 col-lg-1 text-center">Publicar</th>
            <th class="col-xs-2 text-left">Data</th>
            <th class="col-xs-4 col-lg-7 text-left">Título</th>
            <th class="col-xs-2 col-lg-1 text-right">
                <label>
                    <input type="checkbox" class="toggle-destroy-all" data-target=".toggle-destroy">
                    <i class="fa fa-square-o"></i><i class="fa fa-check-square-o"></i>
                </label>
            </th>
        </tr>
        </thead>
        <tbody>
        @foreach($news as $news_item)
            <tr id="news-{{ $news_item->id }}">
                <td class="text-center"><a href="{{ route('admin.news.edit', $news_item->id) }}" class="btn-link icon"><i
                                class="fa fa-pencil-square-o"></i>Editar</a></td>
                <td data-th="Publicar" class="text-center">
                    <form action="{{ route('admin.news.update', $news_item->id) }}" method="POST">
                        <label>
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" class="btn-update" name="active"
                                   value="1" {{ $news_item->active ? 'checked' : '' }}>
                            <i class="fa fa-square-o"></i><i class="fa fa-check-square-o"></i>
                        </label>
                    </form>
                </td>
                <td data-th="Data" class="text-left">{{ $news_item->created_at->format('d/m/Y') }}</td>
                <td data-th="Título" class="text-left">{{ $news_item->title }}</td>
                <td data-th="Excluir" class="text-right">
                    <label>
                        <input type="checkbox" class="toggle-destroy">
                        <i class="fa fa-square-o"></i><i class="fa fa-check-square-o"></i>
                    </label>
                    <button class="btn-destroy" data-url="{{ route('admin.news.destroy', $news_item->id) }}" data-target="#news-{{ $news_item->id }}" style="display: none;"></button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{ $news->links() }}
@endsection
