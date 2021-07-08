
declare(strict_types=1);

class <?php print $className; ?> extends \Fas\DI\Container

{
    protected array $factories = <?php var_export($factories); ?>;
    protected array $lazies = <?php var_export($lazies); ?>;
    const METHODS = <?php var_export($methodMap); ?>;

    <?php foreach ($methods as $id => $code): ?>
    function <?php print $methodMap[$id]; ?>() {
        return (<?php print $code; ?>)($this);
    }
    <?php endforeach; ?>

    public function has(string $id): bool
    {
        return isset(self::METHODS[$id]) ? true : parent::has($id);
    }

    public function isCompiled()
    {
        return true;
    }

    protected function make(string $id) {
        $method = self::METHODS[$id] ?? null;
        return $method ? $this->$method() : parent::make($id);
    }
}
