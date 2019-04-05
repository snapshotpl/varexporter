<?php

declare(strict_types=1);

namespace Brick\VarExporter\ObjectExporter;

use Brick\VarExporter\ObjectExporter;

/**
 * Handles any class through reflection.
 *
 * @internal This class is for internal use, and not part of the public API. It may change at any time without warning.
 */
class ReflectionExporter extends ObjectExporter
{
    /**
     * {@inheritDoc}
     */
    public function supports($object, \ReflectionObject $reflectionObject) : bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function export($object, \ReflectionObject $reflectionObject) : array
    {
        $result = [];

        $result[] = '$class = new \ReflectionClass(\\' . get_class($object) . '::class);';
        $result[] = '$object = $class->newInstanceWithoutConstructor();';

        $current = new \ReflectionObject($object);
        $isParentClass = false;

        while ($current) {
            foreach ($current->getProperties() as $property) {
                if ($isParentClass && ! $property->isPrivate()) {
                    // property already handled in the child class.
                    continue;
                }

                $result[] = '';

                $property->setAccessible(true);

                $name = $property->getName();
                $value = $property->getValue($object);

                $exportedValue = $this->varExporter->doExport($value);

                if ($property->isPublic()) {
                    // public properties AND dynamic properties
                    $exportedValue = $this->varExporter->wrap($exportedValue, '$object->' . $this->escapePropName($name) . ' = ', ';');
                    $result = array_merge($result, $exportedValue);
                } else {
                    if ($isParentClass) {
                        $result[] = '$property = new \ReflectionProperty(\\' . $current->getName() . '::class, ' . var_export($name, true) . ');';
                    } else {
                        $result[] = '$property = $class->getProperty(' . var_export($name, true) . ');';
                    }

                    $result[] = '$property->setAccessible(true);';

                    $exportedValue = $this->varExporter->wrap($exportedValue, '$property->setValue($object, ', ');');
                    $result = array_merge($result, $exportedValue);
                }
            }

            $current = $current->getParentClass();
            $isParentClass = true;
        }

        $result[] = '';
        $result[] = 'return $object;';

        return $this->wrapInClosure($result);
    }
}