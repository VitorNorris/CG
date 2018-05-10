<?php

namespace App\Http\Controllers;

use App\Mail\SendMailTicket;
use Carbon\Carbon;
use App\Services\BaseService;
use Illuminate\Http\Request;
use Milon\Barcode\DNS2D;
use Milon\Barcode\DNS1D;
use Milon\Barcode\Facades;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade as PDF;




class CompletedTicketController extends Controller
{
    private $base_service;

    public function __construct(BaseService $base_service)
    {
        $this->base_service = $base_service;
    }

    public function store(Request $request)
    {
        $data = $request->all();

        $email = $data['email'];
        $email_logado = user_email('EnderecoEmail');

        $data['primeira_parcela'] = (base64_decode($data['primeira_parcela']));
        $cpf = user_info('Cpf');
        $codigo_devedor = user_info('CodigoDevedor');
        $codigo_titulo = $data['codigo_titulo'];
        $plano = $data['plano'];
        $vencimento_primeira = $data['data_vencimento'];
        $valor_primeira = money_format_array($data['primeira_parcela']);

        //RETORNA OS DADOS DO DEVEDOR
        $dados_devedor = $this->base_service->request('GetDadosDevedor', array('pCPF' => $cpf));


        //GRAVA O ACORDO
        $response_int = $this->base_service->request('GravarAcordo', array('pCPF' => $cpf,
            'pCodigoDevedor' => $codigo_devedor, 'pCodigoTitulo' => $codigo_titulo, 'pPlano' => $plano,
            'pVencimentoPrimeira' => $vencimento_primeira, 'pValorPrimeira' => $valor_primeira));

        $resposta_acordo = head($response_int->Codigo);

        if($resposta_acordo != "12") {
            flash()->error('O sistema não conseguiu gravar o acordo. Tente novamente mais tarde');
            return redirect('minhas-dividas');
        }
        //ATUALIZANDO E-MAIL SE FOR E-MAIL NOVO

        if ($email != $email_logado) {
            $response_email = $this->base_service->request('RegistrarNovoEmail', array('pCodigoDevedor' => $codigo_devedor , 'pEmail' => $email));
            //SE RETORNAR UM ERRO DE GRAVAÇÃO DE E-MAIL
            $reposta_email_codigo = head($response_email->Codigo);
            if ($reposta_email_codigo != "12"){
                flash()->error('Não foi possivel resgistrar o e-mail.');
            }

        }
        //

        $response = $this->base_service->request('GetDadosDivida', array('pCPF' => $cpf));

        //VERIFICANDO SE CLIENTE POSSUI DIVIDA
        $existe_divida = json_decode($response->Dividas);

        //CONTAGEM DE QUANTIDADE DE OFERTAS PARA NEGOCIAR DAS DIVIDAS
        if(isset($response->Dividas->Divida)) {
            foreach ($response->Dividas->Divida as $divida) {
                $divida->addChild('OpcoesPagamento');
                $codido_titulo = head($divida->CodigoTitulo);
                $opcoes_pagamento = $this->base_service->request('GetOpcoesPagamento', array('pCodigoTitulo' => $codido_titulo));
                $opcoes_pagamento = count($opcoes_pagamento->OpcoesPagamento->OpcaoPagamento);
                $divida->OpcoesPagamento = $opcoes_pagamento;
            }
        }

        //SE CLIENTE AINDA POSSUIR DIVIDA
        if (isset($existe_divida)){
            //OPÇÕES DE PAGAMENTO DAS DIVIDAS RESTANTES
            $ofertas_dividas = $this->base_service->request('GetOpcoesPagamento', array('pCodigoTitulo' => $codigo_titulo));
        }else{
            $ofertas_dividas = null;
        }

        //RECUPERANDO OS DADOS DO ACORDO
        $dados_acordo = $this->base_service->request('GetDadosAcordo', array('pCodigoTitulo' => $codigo_titulo));
        $codigo_acordo = json_encode(json_decode($dados_acordo->DadosAcordo->CodigoAcordo));

        foreach ($dados_acordo->DadosAcordo->ParcelasAcordo->ParcelaAcordo as $parcela_acordo) {
            if ($parcela_acordo->StatusParcelaAcordo == 'EM ABERTO') {
                $codigo_parcela_acordo = json_encode(json_decode($parcela_acordo->CodigoParcelaAcordo));
                $numero_parcela_acordo = json_encode(json_decode($parcela_acordo->NumeroParcela));
                break;
            }
        }

        //METODO PARA RECUPERAR O  CODIGO DE BARRAS E DATA DE VENCIMENTO DO BOLETO
        $codigo_barras_boleto = $this->base_service->request('GetBoletoAcordo', array('pCodigoParcelaAcordo' => $codigo_parcela_acordo, 'pCodigoAcordo' => $codigo_acordo));

        date_default_timezone_set('America/Sao_Paulo');
        $dt = Carbon::now();
        $data_now = $dt->format('d/m/Y');
        $dia = $data['data_vencimento'];
        $dia = explode("/", $dia);
        $data['vencimento_demais_boletos'] = $dia['0'];
        $data['data_acordo'] = $data_now;
        $dados_acordo = $data;


        $global_ticket['codigo_parcela_acordo'] = $codigo_parcela_acordo;
        $global_ticket['codigo_acordo'] = $codigo_acordo;
        $global_ticket['codigo_titulo'] = $data['codigo_titulo'];


        return view('concluido-boleto', compact('dados_acordo', 'response', 'divida', 'codigo_titulo', 'global_ticket','dados_devedor','codigo_barras_boleto'));
    }

    public function printticket(Request $request)
    {
        $data = $request->all();


        $boleto = base64_decode($data['boleto']);
        $boleto = (json_decode(base64_decode($data['boleto'], true)));
        $codigo_parcela_acordo = $boleto->codigo_parcela_acordo;
        $codigo_acordo = $boleto->codigo_acordo;
        $codigo_titulo = $boleto->codigo_titulo;
        $codigo_barras_boleto = $this->base_service->request('GetBoletoAcordo', array('pCodigoParcelaAcordo' => $codigo_parcela_acordo, 'pCodigoAcordo' => $codigo_acordo));


        //RECUPERANDO DADOS DEVEDOR
        $cpf = user_info('Cpf');
        $dados_devedor = $this->base_service->request('GetDadosDevedor', array('pCPF' => $cpf));
        $user = json_decode(json_encode($dados_devedor));
        //Atalizando dados do cliente na Sessão
        session()->put('user', $user);
        $mail = user_email('EnderecoEmail');


        //RECUPERANDO OS DADOS DO ACORDO
        $dados_acordo = $this->base_service->request('GetDadosAcordo', array('pCodigoTitulo' => $codigo_titulo));


        //RECUPERANDO OS DADOS DIVIDA (PEGAR CODIGO DEVEDOR)
        $cpf = user_info('Cpf');
        $response = $this->base_service->request('GetDadosDivida', array('pCPF' => $cpf));
        $codigo_devedor = (json_encode(json_decode($response->Acordos->DadosAcordo->CodigoDevedor)));



        //RECUPERANDO NUMERO DA PARCELA
        foreach ($dados_acordo->DadosAcordo->ParcelasAcordo->ParcelaAcordo as $parcela_acordo) {
            if ($parcela_acordo->StatusParcelaAcordo == 'EM ABERTO') {
                $codigo_parcela_acordo = json_encode(json_decode($parcela_acordo->CodigoParcelaAcordo));
                $numero_parcela_acordo = json_encode(json_decode($parcela_acordo->NumeroParcela));
                break;
            }
        }


        //Metodo para imprimir os dados do boleto de pagamento do acordo!
        $boleto_impresao = $this->base_service->request('GetDadosValoresContratoOriginal',
            array(
                'pCodigoParcelaAcordo' => $codigo_parcela_acordo,
                'pNumeroParcelaAcordo' => 1,
                'pCodigoAcordo' => $codigo_acordo,
                'pSolicitante' => 'SOLICITANTE',
                'pClienteBoleto' => 'CLIENTE-BOLETO',
                'pTipoNegociacao' => 'ACORDO',
                'pCodigoDevedor' => $codigo_devedor)

        );

        //Se der erro ao gerar o boleto redireciona para tela de acordos
        if ($boleto_impresao->Codigo != "10"){

            flash()->error('Não foi possivel gerar o boleto.');
            return redirect('acordos/acordos-em-andamento');
        }

        //GERANDO E GRAVANDO ARQUIVO PDF
        $nome_boleto = str_replace(' ','',$codigo_barras_boleto->BoletoAcordo->LinhaDigitavel);

        $pdf = PDF::loadView('boleto-anexo-pdf',compact('boleto_impresao','codigo_barras_boleto','dados_acordo','numero_parcela_acordo','cod_barras_boleto','codigo_devedor'));
        $pdf->save('storage/pdf/'.$nome_boleto.'.pdf');





        //**** Envio de boleto via e-mail ****
        if($mail) {
            $receiverAddress = $mail;
            Mail::to($receiverAddress)->send(new SendMailTicket($boleto_impresao, $codigo_barras_boleto, $dados_acordo, $numero_parcela_acordo, $pdf, $nome_boleto, $codigo_devedor));
        }

        // DELETANDO ARQUIVO PDF
        delete_file_pdf($nome_boleto);

        return view('boleto',compact('boleto_impresao','codigo_barras_boleto','dados_acordo','numero_parcela_acordo','cod_barras_boleto','codigo_devedor'));
        //Fim do metodo retorna boleto

    }

}
