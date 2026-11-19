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
 * An omitted URL or url => false omits the action attribute, letting the browser
 * submit to the current document. The security URL retains the URI's path and
 * query without interpreting a leading double slash as a network-path URL.
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
            $signed = $uri->getPath() . ($uri->getQuery() !== '' ? '?' . $uri->getQuery() : '');

            return ['attribute' => null, 'signed' => $signed];
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
