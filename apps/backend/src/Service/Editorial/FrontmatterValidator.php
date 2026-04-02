<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class FrontmatterValidator
{
    private readonly string $schemaPath;

    public function __construct(
        #[Autowire('%kernel.project_dir%/config/schemas/article-frontmatter.schema.json')]
        string $schemaPath,
    ) {
        $this->schemaPath = $schemaPath;
    }

    /**
     * @param array<string, mixed> $frontmatter
     */
    public function validate(array $frontmatter): ValidationResult
    {
        if (!file_exists($this->schemaPath)) {
            return new ValidationResult(false, ['Schema file not found: ' . $this->schemaPath]);
        }

        $schemaJson = file_get_contents($this->schemaPath);
        if ($schemaJson === false) {
            return new ValidationResult(false, ['Cannot read schema file']);
        }

        $schema = json_decode($schemaJson);
        if ($schema === null) {
            return new ValidationResult(false, ['Invalid JSON schema']);
        }

        // Convert frontmatter array to object for json-schema validation
        $data = json_decode(json_encode($frontmatter, JSON_THROW_ON_ERROR));

        $validator = new Validator();
        $validator->validate($data, $schema, Constraint::CHECK_MODE_TYPE_CAST);

        if ($validator->isValid()) {
            return new ValidationResult(true);
        }

        $errors = [];
        foreach ($validator->getErrors() as $error) {
            $path = $error['property'] !== '' ? $error['property'] . ': ' : '';
            $errors[] = $path . $error['message'];
        }

        return new ValidationResult(false, $errors);
    }
}
