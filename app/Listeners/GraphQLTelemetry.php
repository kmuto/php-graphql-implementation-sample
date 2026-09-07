<?php

namespace App\Listeners;

use GraphQL\Language\AST\OperationDefinitionNode;
use Nuwave\Lighthouse\Events\EndExecution;
use Nuwave\Lighthouse\Events\StartExecution;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

class GraphQLTelemetry
{
    private static ?SpanInterface $span = null;

    public function handleStartExecution(StartExecution $event): void
    {
        $operationName = $event->operationName;
        $operationType = null;

        foreach ($event->query->definitions as $definition) {
            if (!$definition instanceof OperationDefinitionNode) {
                continue;
            }
            $operationType = $definition->operation->value ?? (string) $definition->operation;
            if ($operationName === null && $definition->name !== null) {
                $operationName = $definition->name->value;
            }
            break;
        }

        $spanName = rtrim("graphql " . ($operationType ?? '') . " " . ($operationName ?? ''));
        $tracer = \OpenTelemetry\API\Globals::tracerProvider()->getTracer('sg.graphql');
        self::$span = $tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        if ($operationType !== null) {
            self::$span->setAttribute('graphql.operation.type', $operationType);
        }
        if ($operationName !== null) {
            self::$span->setAttribute('graphql.operation.name', $operationName);
        }

        $queryBody = \GraphQL\Language\Printer::doPrint($event->query);
        if (mb_strlen($queryBody) <= 2048) {
            self::$span->setAttribute('graphql.document', $queryBody);
        }
    }

    public function handleEndExecution(EndExecution $event): void
    {
        if (self::$span === null) {
            return;
        }

        if ($event->result->errors !== []) {
            self::$span->setStatus(StatusCode::STATUS_ERROR);
            foreach ($event->result->errors as $error) {
                self::$span->addEvent('graphql.error', [
                    'graphql.error.message' => $error->getMessage(),
                    'graphql.error.path' => implode('.', $error->getPath() ?? []),
                ]);
            }
        }

        self::$span->end();
        self::$span = null;
    }
}
