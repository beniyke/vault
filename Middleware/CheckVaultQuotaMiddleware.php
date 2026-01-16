<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Check Vault Quota Middleware.
 * Validates storage quota before allowing file uploads.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Vault\Middleware;

use Closure;
use Core\Middleware\MiddlewareInterface;
use Helpers\Http\Request;
use Helpers\Http\Response;
use Throwable;
use Vault\Exceptions\QuotaExceededException;
use Vault\Services\VaultManagerService;

class CheckVaultQuotaMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly VaultManagerService $vaultManager
    ) {
    }

    public function handle(Request $request, Response $response, Closure $next): mixed
    {
        if (!$request->hasFile()) {
            return $next($request, $response);
        }

        $accountId = $this->getAccountId($request);

        if (! $accountId) {
            return $next($request, $response);
        }

        $totalSize = 0;
        $files = $request->file();

        foreach ($files as $file) {
            if (is_array($file)) {
                foreach ($file as $f) {
                    $totalSize += $f->getSize();
                }
            } else {
                $totalSize += $file->getSize();
            }
        }

        try {
            if (! $this->vaultManager->canUpload($accountId, $totalSize)) {
                return $response->json([
                    'error' => 'Storage quota exceeded',
                    'message' => 'Insufficient storage space for this upload'
                ], 413);
            }
        } catch (QuotaExceededException $e) {
            return $response->json([
                'error' => 'Storage quota exceeded',
                'message' => $e->getMessage()
            ], 413);
        } catch (Throwable $e) {
            logger('vault.log')->error('Vault quota check failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
        }

        return $next($request, $response);
    }

    private function getAccountId(Request $request): ?string
    {
        $resolver = config('vault.account_resolver');

        if (is_callable($resolver)) {
            $id = $resolver($request);

            return $id ? (string) $id : null;
        }

        return $request->header('X-Account-ID');
    }
}
