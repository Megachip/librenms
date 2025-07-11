<?php

/**
 * LnmsCommand.php
 *
 * Convenience class for common command code
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2019 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Console;

use Illuminate\Console\Command;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LibreNMS\Util\Debug;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;
use Validator;

abstract class LnmsCommand extends Command
{
    protected $developer = false;

    /** @var string[][]|callable[]|null */
    protected $optionValues;
    /** @var string[][]|callable[]|null */
    protected $optionDefaults;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->setDescription(__('commands.' . $this->getName() . '.description'));
    }

    public function isHidden(): bool
    {
        $env = $this->getLaravel() ? $this->getLaravel()->environment() : getenv('APP_ENV');

        return $this->hidden || ($this->developer && $env !== 'production');
    }

    /**
     * Adds an argument. If $description is null, translate commands.command-name.arguments.name
     * If you want the description to be empty, just set an empty string
     *
     * @param  string  $name
     * @param  int|null  $mode
     * @param  string  $description
     * @param  mixed|null  $default
     * @param  array|\Closure  $suggestedValues
     * @return $this
     *
     * @throws InvalidArgumentException When argument mode is not valid
     */
    public function addArgument(string $name, ?int $mode = null, string $description = '', mixed $default = null, array|\Closure $suggestedValues = []): static
    {
        // use a generated translation location by default
        if (empty($description)) {
            $description = __('commands.' . $this->getName() . '.arguments.' . $name);
        }

        parent::addArgument($name, $mode, $description, $default, $suggestedValues);

        return $this;
    }

    /**
     * Adds an option. If $description is null, translate commands.command-name.arguments.name
     * If you want the description to be empty, just set an empty string
     *
     * @param  string  $name
     * @param  array|string|null  $shortcut
     * @param  int|null  $mode
     * @param  string  $description
     * @param  mixed|null  $default
     * @param  array|\Closure  $suggestedValues
     * @return $this
     *
     * @throws InvalidArgumentException If option mode is invalid or incompatible
     */
    public function addOption(string $name, array|string|null $shortcut = null, ?int $mode = null, string $description = '', mixed $default = null, array|\Closure $suggestedValues = []): static
    {
        // use a generated translation location by default
        if (empty($description)) {
            $description = __('commands.' . $this->getName() . '.options.' . $name);
        }

        // inject our custom InputOption to allow callable option enums
        $this->getDefinition()->addOption(
            new DynamicInputOption(
                $name,
                $shortcut,
                $mode,
                $description,
                $default,
                $this->getCallable('Defaults', $name),
                $this->getCallable('Values', $name),
                $suggestedValues,
            )
        );

        return $this;
    }

    /**
     * Validate the input of this command.  Uses Laravel input validation
     * merging the arguments and options together to check.
     */
    protected function validate(array $rules, array $messages = []): array
    {
        // auto create option value rules if they don't exist
        if (isset($this->optionValues)) {
            foreach (array_keys($this->optionValues) as $option) {
                $callable = $this->getCallable('Values', $option);
                if (empty($rules[$option]) && $callable) {
                    $values = call_user_func($callable);
                    $rules[$option] = Rule::in($values);
                    $messages[$option . '.in'] = trans('commands.lnms.validation-errors.optionValue', [
                        'option' => $option,
                        'values' => implode(', ', $values),
                    ]);
                }
            }
        }

        $error_messages = trans('commands.' . $this->getName() . '.validation-errors');
        $validator = Validator::make(
            $this->arguments() + $this->options(),
            $rules,
            is_array($error_messages) ? array_merge($error_messages, $messages) : $messages
        );

        try {
            $validator->validate();

            return $validator->validated();
        } catch (ValidationException $e) {
            collect($validator->getMessageBag()->all())->each(function ($message) {
                $this->error($message);
            });
            exit(1);
        }
    }

    protected function configureOutputOptions(): void
    {
        $verbosity = $this->getOutput()->getVerbosity();

        if ($verbosity === OutputInterface::VERBOSITY_QUIET) {
            \Log::setDefaultDriver('stack'); // this omits stdout
            Debug::setCliQuietOutput();

            return;
        }

        \Log::setDefaultDriver('console');

        if ($verbosity >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
            Debug::set();
            if ($verbosity >= OutputInterface::VERBOSITY_DEBUG) {
                Debug::setVerbose();
            }
        }
    }

    private function getCallable(string $type, string $name): ?callable
    {
        if (empty($this->{'option' . $type}[$name])) {
            return null;
        }

        $values = $this->{'option' . $type}[$name];
        if (is_callable($values)) {
            return $values;
        }

        return function () use ($values) {
            return $values;
        };
    }
}
