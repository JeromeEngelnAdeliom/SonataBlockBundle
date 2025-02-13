<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\BlockBundle\Tests\Block;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sonata\BlockBundle\Block\BlockContext;
use Sonata\BlockBundle\Block\BlockRenderer;
use Sonata\BlockBundle\Block\BlockServiceManagerInterface;
use Sonata\BlockBundle\Block\Service\BlockServiceInterface;
use Sonata\BlockBundle\Exception\Strategy\StrategyManagerInterface;
use Sonata\BlockBundle\Model\BlockInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit test of BlockRenderer class.
 */
final class BlockRendererTest extends TestCase
{
    /**
     * @var MockObject&BlockServiceManagerInterface
     */
    private $blockServiceManager;

    /**
     * @var MockObject&LoggerInterface
     */
    private $logger;

    /**
     * @var MockObject&StrategyManagerInterface
     */
    private $exceptionStrategyManager;

    private BlockRenderer $renderer;

    /**
     * Setup test object.
     */
    protected function setUp(): void
    {
        $this->blockServiceManager = $this->createMock(BlockServiceManagerInterface::class);
        $this->exceptionStrategyManager = $this->createMock(StrategyManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->renderer = new BlockRenderer($this->blockServiceManager, $this->exceptionStrategyManager, $this->logger);
    }

    /**
     * Test rendering a block without errors.
     */
    public function testRenderWithoutErrors(): void
    {
        // mock a block service that returns a response
        $response = $this->createMock(Response::class);
        $service = $this->createMock(BlockServiceInterface::class);
        $service->expects(static::once())->method('load');
        $service->expects(static::once())->method('execute')->willReturn($response);
        $this->blockServiceManager->expects(static::once())->method('get')->willReturn($service);

        // mock a block object
        $block = $this->createMock(BlockInterface::class);
        $blockContext = new BlockContext($block, [
            'template' => 'fake_template',
        ]);

        $result = $this->renderer->render($blockContext);

        static::assertSame($response, $result, 'Should return the response from the block service');
    }

    /**
     * Test rendering a block that throws an exception.
     */
    public function testRenderBlockWithException(): void
    {
        // mock a block service that throws an user exception
        $service = $this->createMock(BlockServiceInterface::class);
        $service->expects(static::once())->method('load');

        $exception = $this->createMock(\Exception::class);
        $service->expects(static::once())
            ->method('execute')
            ->willReturnCallback(static function () use ($exception): void {
                throw $exception;
            });

        $this->blockServiceManager->expects(static::once())->method('get')->willReturn($service);

        // mock the exception strategy manager to return a response when given the correct exception
        $response = $this->createMock(Response::class);
        $this->exceptionStrategyManager->expects(static::once())
            ->method('handleException')
            ->with(static::equalTo($exception))
            ->willReturn($response);

        // mock the logger to ensure a crit message is logged
        $this->logger->expects(static::once())->method('error');

        // mock a block object
        $block = $this->createMock(BlockInterface::class);
        $blockContext = new BlockContext($block, [
            'template' => 'fake_template',
        ]);

        $result = $this->renderer->render($blockContext);

        static::assertSame($response, $result, 'Should return the response provider by the exception manager');
    }
}
