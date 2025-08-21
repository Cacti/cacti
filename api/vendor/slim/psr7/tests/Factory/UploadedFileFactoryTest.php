<?php

/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim-Psr7/blob/master/LICENSE.md (MIT License)
 */

declare(strict_types=1);

namespace Slim\Tests\Psr7\Factory;

use Interop\Http\Factory\UploadedFileFactoryTestCase;
use InvalidArgumentException;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\StreamInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UploadedFileFactory;

use function fopen;
use function fwrite;
use function rewind;
use function sys_get_temp_dir;
use function tempnam;

class UploadedFileFactoryTest extends UploadedFileFactoryTestCase
{
    use ProphecyTrait;

    protected function createUploadedFileFactory(): UploadedFileFactory
    {
        return new UploadedFileFactory();
    }

    protected function createStream($content): StreamInterface
    {
        $file = tempnam(sys_get_temp_dir(), 'Slim_Http_UploadedFileTest_');
        $resource = fopen($file, 'r+');
        fwrite($resource, $content);
        rewind($resource);

        return (new StreamFactory())->createStreamFromResource($resource);
    }

    /**
     * Prophesize a `\Psr\Http\Message\StreamInterface` with a `getMetadata` method prophecy.
     *
     * @param string $argKey Argument for the method prophecy.
     * @param mixed $returnValue Return value of the `getMetadata` method.
     *
     * @return StreamInterface
     */
    protected function prophesizeStreamInterfaceWithGetMetadataMethod(string $argKey, $returnValue): StreamInterface
    {
        $streamProphecy = $this->prophesize(StreamInterface::class);

        /** @noinspection PhpUndefinedMethodInspection */
        $streamProphecy
            ->getMetadata($argKey)
            ->willReturn($returnValue)
            ->shouldBeCalled();

        /** @var StreamInterface $stream */
        $stream = $streamProphecy->reveal();

        return $stream;
    }

    public function testCreateUploadedFileWithInvalidUri()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File is not readable.');

        // Prophesize a `\Psr\Http\Message\StreamInterface` with a `getMetadata` method prophecy.
        $streamProphecy = $this->prophesize(StreamInterface::class);

        /** @noinspection PhpUndefinedMethodInspection */
        $streamProphecy
            ->getMetadata('uri')
            ->willReturn(null)
            ->shouldBeCalled();

        /** @var StreamInterface $stream */
        $stream = $streamProphecy->reveal();

        $this->factory->createUploadedFile($stream);
    }

    public function testCreateUploadedFileWithNonReadableFile()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File is not readable.');

        // Prophesize a `\Psr\Http\Message\StreamInterface` with a `getMetadata` and `isReadable` method prophecies.
        $streamProphecy = $this->prophesize(StreamInterface::class);

        /** @noinspection PhpUndefinedMethodInspection */
        $streamProphecy
            ->getMetadata('uri')
            ->willReturn('non-readable')
            ->shouldBeCalled();

        /** @noinspection PhpUndefinedMethodInspection */
        $streamProphecy
            ->isReadable()
            ->willReturn(false)
            ->shouldBeCalled();

        /** @var StreamInterface $stream */
        $stream = $streamProphecy->reveal();

        $this->factory->createUploadedFile($stream);
    }
}
