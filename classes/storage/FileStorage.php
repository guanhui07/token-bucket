<?php

namespace bandwidthThrottle\tokenBucket\storage;

use bandwidthThrottle\tokenBucket\lock\Flock;

/**
 * File based storage which can be shared among processes.
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1335STSwu9hST4vcMRppEPgENMHD2r1REK Donations
 * @license WTFPL
 */
class FileStorage implements Storage
{
 
    /**
     * @var Mutex The mutex.
     */
    private $mutex;
    
    /**
     * @var resource The file handle.
     */
    private $fileHandle;
    
    /**
     * @var string The file path.
     */
    private $path;
    
    /**
     * Sets the file path and opens it.
     *
     * If the file does not exist yet, it will be created. This is an atomic
     * operation.
     *
     * @param string $path The file path.
     * @throws StorageException Failed to open the file.
     */
    public function __construct($path)
    {
        $this->path = $path;
        $this->open();
    }
    
    /**
     * Opens the file and initializes the mutex.
     *
     * @throws StorageException Failed to open the file.
     */
    private function open()
    {
        $this->fileHandle = fopen($this->path, "c+");
        if (!is_resource($this->fileHandle)) {
            throw new StorageException("Could not open '$this->path'.");

        }
        $this->mutex = new Flock($this->fileHandle);
    }
    
    /**
     * Closes the file handle.
     *
     * @internal
     */
    public function __destruct()
    {
        fclose($this->fileHandle);
    }
    
    public function isBootstrapped()
    {
        $stats = fstat($this->fileHandle);
        return $stats["size"] > 0;
    }
    
    public function bootstrap($microtime)
    {
        $this->open(); // remove() could have deleted the file.
        $this->setMicrotime($microtime);
    }
    
    public function remove()
    {
        // Truncate to notify isBootstrapped() about the new state.
        if (!ftruncate($this->fileHandle, 0)) {
            throw new StorageException("Could not truncate $this->path");

        }
        if (!unlink($this->path)) {
            throw new StorageException("Could not delete $this->path");
        }
    }

    public function setMicrotime($microtime)
    {
        if (fseek($this->fileHandle, 0) !== 0) {
            throw new StorageException("Could not move to beginning of the file.");
        }

        $data = pack("d", $microtime);
        assert(8 === strlen($data)); // $data is a 64 bit double.

        $result = fwrite($this->fileHandle, $data, strlen($data));
        if ($result !== strlen($data)) {
            throw new StorageException("Could not write to storage.");
        }
    }
    
    public function getMicrotime()
    {
        if (fseek($this->fileHandle, 0) !== 0) {
            throw new StorageException("Could not move to beginning of the file.");

        }
        $data = fread($this->fileHandle, 8);
        if ($data === false) {
            throw new StorageException("Could not read from storage.");
        }
        if (strlen($data) !== 8) {
            throw new StorageException("Could not read 64 bit from storage.");

        }
        $unpack = unpack("d", $data);
        if (!is_array($unpack) || !array_key_exists(1, $unpack)) {
            throw new StorageException("Could not unpack storage content.");

        }
        return $unpack[1];
    }
    
    public function getMutex()
    {
        return $this->mutex;
    }
}
