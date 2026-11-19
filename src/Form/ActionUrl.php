<?php
declare(strict_types=1);

namespace FormCompose\Form;

use Cake\Routing\Router;
use FormCompose\Binding\FormBinding;
use FormCompose\Configuration\FormConfig;
use InvalidArgumentException;

/**
 * Resolves both the form action attribute and the URL used for its security token.
 *
 * FormConstruction passes route arrays or URL strings through CakePHP routing.
 * An omitted URL uses the current request target; url => false omits the action
 * attribute. Default actions normalize leading slashes and escape backslashes so
 * browsers cannot interpret request paths as cross-origin URLs. The security URL
 * retains the resulting submission path and query string.
 */
final class ActionUrl
{
    /**
     * Build an action or retain the current request target when action is omitted.
     *
     * @return array{attribute: ?string, signed: string}
     */
    public static function resolve(FormBinding $binding, FormConfig $config): array
    {
        $url = $config->url;
        if ($url === null || $url === false) {
            $uri = $binding->request->getUri();
            $path = $uri->getPath();
            if ($url === null) {
                $path = '/' . ltrim(str_replace('\\', '%5C', $path), '/');
            }

            $signed = $path . ($uri->getQuery() !== '' ? '?' . $uri->getQuery() : '');

            return ['attribute' => $url === false ? null : $signed, 'signed' => $signed];
        }

        $action = Router::url($url);
        $parts = parse_url($action);
        if (!is_array($parts)) {
            throw new InvalidArgumentException('Invalid form action URL.');
        }

        $signed = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

        return ['attribute' => $action, 'signed' => $signed];
    }
}
