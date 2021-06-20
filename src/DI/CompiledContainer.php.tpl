
declare(strict_types=1);

namespace Fas\DI;

class <?php print $className; ?> extends <?php print $baseClass; ?>

{
    protected function make(string $id)
    {
        switch ($id) {
            <?php foreach ($factories as $id => $callback): ?>
            case <?php print var_export($id); ?>: return <?php print $callback; ?>;
            <?php endforeach; ?>
            default: return parent::make($id);
        }
    }

    public function has(string $id): bool
    {
        switch ($id) {
            <?php foreach ($factories as $id => $callback): ?>
            case <?php print var_export($id); ?>: return true;
            <?php endforeach; ?>
            default: return parent::has($id);
        }
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
