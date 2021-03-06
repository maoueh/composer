<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Test\Util;

use Composer\Util\RemoteFilesystem;
use Composer\Test\TestCase;

class RemoteFilesystemTest extends \PHPUnit_Framework_TestCase
{
    public function testGetOptionsForUrl()
    {
        $io = $this->getMock('Composer\IO\IOInterface');
        $io
            ->expects($this->once())
            ->method('hasAuthorization')
            ->will($this->returnValue(false))
        ;
        $io
            ->expects($this->once())
            ->method('getLastUsername')
            ->will($this->returnValue(null))
        ;

        $this->assertEquals(array(), $this->callGetOptionsForUrl($io, array('http://example.org')));
    }

    public function testGetOptionsForUrlWithAuthorization()
    {
        $io = $this->getMock('Composer\IO\IOInterface');
        $io
            ->expects($this->once())
            ->method('hasAuthorization')
            ->will($this->returnValue(true))
        ;
        $io
            ->expects($this->once())
            ->method('getAuthorization')
            ->will($this->returnValue(array('username' => 'login', 'password' => 'password')))
        ;

        $options = $this->callGetOptionsForUrl($io, array('http://example.org'));
        $this->assertContains('Authorization: Basic', $options['http']['header']);
    }

    public function testGetOptionsForUrlWithLastUsername()
    {
        $io = $this->getMock('Composer\IO\IOInterface');
        $io
            ->expects($this->once())
            ->method('hasAuthorization')
            ->will($this->returnValue(false))
        ;
        $io
            ->expects($this->any())
            ->method('getLastUsername')
            ->will($this->returnValue('login'))
        ;
        $io
            ->expects($this->any())
            ->method('getLastPassword')
            ->will($this->returnValue('password'))
        ;
        $io
            ->expects($this->once())
            ->method('setAuthorization')
        ;

        $options = $this->callGetOptionsForUrl($io, array('http://example.org'));
        $this->assertContains('Authorization: Basic', $options['http']['header']);
    }

    public function testCallbackGetFileSize()
    {
        $fs = new RemoteFilesystem($this->getMock('Composer\IO\IOInterface'));
        $this->callCallbackGet($fs, STREAM_NOTIFY_FILE_SIZE_IS, 0, '', 0, 0, 20);
        $this->assertAttributeEquals(20, 'bytesMax', $fs);
    }

    public function testCallbackGetNotifyProgress()
    {
        $io = $this->getMock('Composer\IO\IOInterface');
        $io
            ->expects($this->once())
            ->method('overwrite')
        ;

        $fs = new RemoteFilesystem($io);
        $this->setAttribute($fs, 'bytesMax', 20);
        $this->setAttribute($fs, 'progress', true);

        $this->callCallbackGet($fs, STREAM_NOTIFY_PROGRESS, 0, '', 0, 10, 20);
        $this->assertAttributeEquals(50, 'lastProgress', $fs);
    }

    public function testCallbackGetNotifyFailure404()
    {
        $fs = new RemoteFilesystem($this->getMock('Composer\IO\IOInterface'));
        $this->setAttribute($fs, 'firstCall', false);

        try {
            $this->callCallbackGet($fs, STREAM_NOTIFY_FAILURE, 0, '', 404, 0, 0);
            $this->fail();
        } catch (\Exception $e) {
            $this->assertInstanceOf('Composer\Downloader\TransportException', $e);
            $this->assertContains('URL not found', $e->getMessage());
        }
    }

    public function testCallbackGetNotifyFailure404FirstCall()
    {
        $io = $this->getMock('Composer\IO\IOInterface');
        $io
            ->expects($this->once())
            ->method('getAuthorization')
            ->will($this->returnValue(array('username' => null)))
        ;
        $io
            ->expects($this->once())
            ->method('isInteractive')
            ->will($this->returnValue(false))
        ;

        $fs = new RemoteFilesystem($io);
        $this->setAttribute($fs, 'firstCall', true);

        try {
            $this->callCallbackGet($fs, STREAM_NOTIFY_FAILURE, 0, '', 404, 0, 0);
            $this->fail();
        } catch (\Exception $e) {
            $this->assertInstanceOf('Composer\Downloader\TransportException', $e);
            $this->assertContains('URL required authentication', $e->getMessage());
            $this->assertAttributeEquals(false, 'firstCall', $fs);
        }
    }

    public function testGetContents()
    {
        $fs = new RemoteFilesystem($this->getMock('Composer\IO\IOInterface'));

        $this->assertContains('RFC 2606', $fs->getContents('http://example.org', 'http://example.org'));
    }

    public function testCopy()
    {
        $fs = new RemoteFilesystem($this->getMock('Composer\IO\IOInterface'));

        $file = tempnam(sys_get_temp_dir(), 'c');
        $this->assertTrue($fs->copy('http://example.org', 'http://example.org', $file));
        $this->assertFileExists($file);
        $this->assertContains('RFC 2606', file_get_contents($file));
        unlink($file);
    }

    protected function callGetOptionsForUrl($io, array $args = array())
    {
        $fs = new RemoteFilesystem($io);
        $ref = new \ReflectionMethod($fs, 'getOptionsForUrl');
        $ref->setAccessible(true);

        return $ref->invokeArgs($fs, $args);
    }

    protected function callCallbackGet(RemoteFilesystem $fs, $notificationCode, $severity, $message, $messageCode, $bytesTransferred, $bytesMax)
    {
        $ref = new \ReflectionMethod($fs, 'callbackGet');
        $ref->setAccessible(true);
        $ref->invoke($fs, $notificationCode, $severity, $message, $messageCode, $bytesTransferred, $bytesMax);
    }

    protected function setAttribute($object, $attribute, $value)
    {
        $attr = new \ReflectionProperty($object, $attribute);
        $attr->setAccessible(true);
        $attr->setValue($object, $value);
    }
}
