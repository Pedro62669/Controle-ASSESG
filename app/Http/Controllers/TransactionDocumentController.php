<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega o comprovante de uma movimentação.
 *
 * Os anexos ficam fora do diretório público: só chegam ao navegador por esta
 * rota, sempre com o usuário autenticado e ativo.
 */
class TransactionDocumentController extends Controller
{
    public function __invoke(Request $request, Transaction $transaction): StreamedResponse
    {
        abort_if(blank($transaction->document_path), 404, 'Esta movimentação não possui comprovante.');

        $disk = Storage::disk('local');

        abort_unless($disk->exists($transaction->document_path), 404, 'Comprovante não encontrado no armazenamento.');

        return $disk->response(
            $transaction->document_path,
            $transaction->document_name ?? basename($transaction->document_path),
        );
    }
}
