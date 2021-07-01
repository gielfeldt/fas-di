
declare(strict_types=1);

namespace Fas\DI;

class <?php print $className; ?> extends <?php print $baseClass; ?>

{

    const FACTORY_REFS = <?php var_export($factory_refs); ?>;

    <?php foreach ($factory_refs as $id => $method): ?>
    <?php $callback = $factories[$method]; ?>
    // <?php print $id; ?>

    function <?php print $method; ?>()
    {
        <?php print $callback; ?>

    }

    <?php endforeach ?>

    public function has(string $id): bool
    {
        return isset(static::FACTORY_REFS[$id]) || parent::has($id);
    }

    protected function make(string $id) {
        $method = static::FACTORY_REFS[$id] ?? null;
        if ($method) {
            try {
                $this->markResolving($id);
                return $this->$method();
            } finally {
                $this->unmarkResolving($id);
            }
        }
        return parent::make($id);
    }

    public function save(string $filename)
    {
        throw new \BadMethodCallException("Cannot compile an already compiled container");
    }

    public function isCompiled(): bool
    {
        return true;
    }
}

return new <?php print $className; ?>;
