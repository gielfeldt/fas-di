
namespace Fas\DI;

class <?php print $className; ?> extends Container
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

    public function has($id)
    {
        return isset(static::FACTORY_REFS[$id]) || parent::has($id);
    }

    public function make(string $id) {
        $method = static::FACTORY_REFS[$id] ?? null;
        return $method ? $this->$method() : parent::make($id);
    }

    public function compile(string $filename = null)
    {
        throw new \BadMethodCallException("Cannot compile an already compiled container");
    }

    public function isCompiled()
    {
        return true;
    }
}

return new <?php print $className; ?>;
