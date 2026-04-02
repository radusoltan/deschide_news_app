<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Entity\ImportantArticlesList;
use App\Repository\ImportantArticlesListRepository;
use App\Validator\ImportantArticlesCount;
use App\Validator\ImportantArticlesCountValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportantArticlesCountValidatorTest extends TestCase
{
    private ImportantArticlesListRepository $repository;
    private ImportantArticlesCountValidator $validator;
    private ExecutionContextInterface $context;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(ImportantArticlesListRepository::class);
        $this->validator = new ImportantArticlesCountValidator($this->repository);
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->validator->initialize($this->context);
    }

    public function testValidateThrowsOnWrongConstraint(): void
    {
        $wrongConstraint = $this->createStub(Constraint::class);
        $entity = $this->createStub(ImportantArticlesList::class);

        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate($entity, $wrongConstraint);
    }

    public function testValidateThrowsOnWrongValue(): void
    {
        $constraint = new ImportantArticlesCount();

        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate('not-an-entity', $constraint);
    }

    public function testValidateSkipsExistingEntity(): void
    {
        $entity = $this->createStub(ImportantArticlesList::class);
        $entity->method('getId')->willReturn(42); // Existing entity

        $constraint = new ImportantArticlesCount();

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate($entity, $constraint);
    }

    public function testValidateAllowsAddingWhenBelowMax(): void
    {
        $entity = $this->createStub(ImportantArticlesList::class);
        $entity->method('getId')->willReturn(null); // New entity

        $this->repository->method('count')->willReturn(10); // 10 existing

        $constraint = new ImportantArticlesCount();

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate($entity, $constraint);
    }

    public function testValidateAddsViolationWhenExceedingMax(): void
    {
        $entity = $this->createStub(ImportantArticlesList::class);
        $entity->method('getId')->willReturn(null); // New entity

        $this->repository->method('count')->willReturn(25); // Adding would make 26

        $constraint = new ImportantArticlesCount();

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())
            ->method('setParameter')
            ->with('{{ max }}', '25')
            ->willReturn($violationBuilder);
        $violationBuilder->expects($this->once())
            ->method('addViolation');

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->maxMessage)
            ->willReturn($violationBuilder);

        $this->validator->validate($entity, $constraint);
    }

    public function testValidateAllowsAtExactMax(): void
    {
        $entity = $this->createStub(ImportantArticlesList::class);
        $entity->method('getId')->willReturn(null);

        // 24 existing + 1 new = 25 = max, should NOT violate
        $this->repository->method('count')->willReturn(24);

        $constraint = new ImportantArticlesCount();

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate($entity, $constraint);
    }

    public function testConstraintProperties(): void
    {
        $constraint = new ImportantArticlesCount();

        $this->assertSame(5, $constraint->min);
        $this->assertSame(25, $constraint->max);
        $this->assertStringContainsString('at least', $constraint->minMessage);
        $this->assertStringContainsString('more than', $constraint->maxMessage);
    }

    public function testConstraintTargetsClass(): void
    {
        $constraint = new ImportantArticlesCount();

        $this->assertSame(Constraint::CLASS_CONSTRAINT, $constraint->getTargets());
    }
}
