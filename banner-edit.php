<!-- Não indexar -->
@section('no-index')
<meta name="robots" content="noindex, nofollow" />
@endsection
<!--Fim Não indexar -->

@extends('layouts.app')
@section('title','Banner Promocional')
@push('styles')
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
@endpush
@section('body','pg-internas acordos')
<!-- Content -->
@section('content')
<div class="container-fluid">
    <div class="row">
        <section class="conteudo">

            <!-- mobile -->
            <div class="col-xs-12 visible-xs visible-sm nopadding">
                <div class="title title-mobile text-center animated fadeInDown">
                    <h1>Editar Banner</h1>
                </div>
            </div>
            <div>
                <div class="banner-promocional col-xs-12 col-md-4 col-md-offset-4 banner-img">
                    <a href="#!"><img class="img-responsive text-center" src="{{ asset($banner->path_file) }}"
                                      alt="{{$banner->title}}"></a>
                </div>
            </div>
            <form enctype="multipart/form-data" action="{{route('site.banner.update')}}" method="POST">
                {{ csrf_field() }}
                <div class="margin-20">
                    <div class="col-xs-12 col-md-4 col-md-offset-4">
                        <h4>Escolher arquivo:</h4>

                        <input type="file" name="images" value="{{$banner->path_file}}" class="input-banner">
                    </div>
                </div>


                <div class="col-xs-12 col-md-4 col-md-offset-4 inputs-banner">
                    <h4>Título do banner:</h4>
                    <p> ( Usado para o <b>alt</b> da imagem )</p>
                    <input type="text" name="title" value="{{$banner->title}}" class=" form-control">
                </div>


                <div class="col-xs-12 col-md-4 col-md-offset-4 ">
                    <h4>Link do Banner:</h4>
                    <p> ( Inserir <b>url</b> do link )</p>
                    <input type="text" name="url" value="{{$banner->url}}" class=" form-control">
                </div>



                <div class="col-xs-12 col-md-4 col-md-offset-4">
                    <a href="{{route('site.banner.edit')}}">
                        <button type="submit" class="btn btn-block btn-secondary margin-top-20">Gravar</button>
                    </a>

                    <a href="{{route('site.banner.index')}}" class="btn btn-block btn-secondary margin-top-20" style="background-color: #00A4EC"><i class="fa fa-chevron-left" aria-hidden="true" style="color: #ffffff"></i><span style="color: #ffffff"> Voltar </span></a>
                </div>



            </form>




        </section>
    </div>
</div>

@include('_includes.form-delete', ['modal_title' => 'Tem certeza que deseja remover esse Feriado?'])
@include('_includes.form-logout')
@endsection
@push('scripts')
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script>

    $(document).ready(function () {
        /* if (jQuery.ui) {*/
        $('.sortable').sortable({
            cursor: 'move',
            opacity: 0.5,
            stop: function (event, ui) {
                var array_ids = $(this).sortable('toArray');
                var orders = [];
                var url = $(this).attr('data-url');

                $.each(array_ids, function (index, value) {
                    var order = index + 1;
                    var array = value.split('-');
                    var id = array[array.length - 1];

                    orders.push({
                        id: id,
                        order: order
                    });
                });

                $.ajax({
                    url: url,
                    type: 'put',
                    dataType: 'json',
                    data: {
                        data: orders
                    },
                    headers: {
                        'X-CSRF-TOKEN': '{{csrf_token()}}'

                    }
                }).done(function (response) {
                    console.log(response);

                });
                console.log(orders);
            }
        });

        $('.sortable').sortable('disable');
        /* }*/
    });

    $('.toggle-sortable').on('click', function () {

        $('.toggle-sortable').toggleClass('active');

        if ($('.sortable').hasClass('ui-sortable-disabled')) {
            $('.sortable').sortable('enable');
            $('#btn-faq').html('ORDENAÇÃO ATIVA <i class="fa fa-check" aria-hidden="true"></i>');
        } else {
            $('.sortable').sortable('disable');
            $('#btn-faq').text('HABILITAR ORDENAÇÃO');
        }

        //teste


        //fim


    });

</script>
@endpush
