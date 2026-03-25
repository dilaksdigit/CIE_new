<?php
// SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §4.2 — structured parse outcome for SemrushParserService
namespace App\Services;

final class SemrushParseResult
{
    /**
     * @param array<int, array<string, mixed>> $insertRows
     * @param array<int, string> $rowErrors
     * @param list<array<string, mixed>> $parsedRows logical rows after column map (pre-insert)
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorMessage = null,
        public readonly array $rowErrors = [],
        public readonly array $insertRows = [],
        public readonly ?string $importBatch = null,
        public readonly ?string $importBatchId = null,
        public readonly array $parsedRows = [],
    ) {
    }

    public function hasErrors(): bool
    {
        return !$this->success;
    }

    public function getFirstError(): string
    {
        if ($this->errorMessage !== null && $this->errorMessage !== '') {
            return $this->errorMessage;
        }
        return $this->rowErrors[0] ?? 'Validation failed';
    }

    public function getRowCount(): int
    {
        return count($this->insertRows);
    }

    public function getBatch(): ?string
    {
        return $this->importBatch;
    }

    public function getRows(): array
    {
        return $this->insertRows;
    }
}
