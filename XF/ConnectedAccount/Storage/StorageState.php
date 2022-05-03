<?php

namespace Truonglv\Api\XF\ConnectedAccount\Storage;

class StorageState extends XFCP_StorageState
{
    public function setTApiStorageType(string $storageType): void
    {
        $this->storageType = $storageType;
    }
}
