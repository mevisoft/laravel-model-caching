<?php namespace GeneaLabs\LaravelModelCaching\Traits;

use GeneaLabs\LaravelPivotEvents\Traits\FiresPivotEventsTrait;

trait CachedPivotOperations
{
    use FiresPivotEventsTrait {
        FiresPivotEventsTrait::sync as traitSync;
        FiresPivotEventsTrait::attach as traitAttach;
        FiresPivotEventsTrait::detach as traitDetach;
        FiresPivotEventsTrait::updateExistingPivot as traitUpdateExistingPivot;
    }

    protected $isSyncing = false;

    protected function flushCacheForPivotOperation(): void
    {
        if (method_exists($this->parent, 'flushCache')) {
            $this->parent->flushCache();
        }

        $relatedModel = $this->getRelated();

        if (method_exists($relatedModel, 'flushCache')) {
            $relatedModel->flushCache();
        }
    }

    protected function syncParche($ids, $detaching = true)
    {
        if (false === $this->parent->fireModelEvent('pivotSyncing', true, $this->getRelationName())) {
            return false;
        }

        $parentResult = parent::sync($ids, $detaching);

        if (array_filter($parentResult)) {
            $this->parent->fireModelEvent('pivotSynced', false, $this->getRelationName(), $parentResult);
        }

        return $parentResult;
    }

    public function sync($ids, $detaching = true)
    {
        $wasCachable = $this->isCachable;
        $this->isCachable = false;
        $this->isSyncing = true;

        try {
            $result = $this->syncParche($ids, $detaching);
        } finally {
            $this->isSyncing = false;
            $this->isCachable = $wasCachable;
        }

        $this->flushCacheForPivotOperation();

        return $result;
    }

    public function attach($ids, array $attributes = [], $touch = true)
    {
        $wasCachable = $this->isCachable;
        $this->isCachable = false;

        try {
            $result = $this->traitAttach($ids, $attributes, $touch);
        } finally {
            $this->isCachable = $wasCachable;
        }

        if (! $this->isSyncing) {
            $this->flushCacheForPivotOperation();
        }

        return $result;
    }
    public function detachParche($ids = null, $touch = true)
    {
        if (is_null($ids)) {
            $ids = $this->query->pluck($this->query->qualifyColumn($this->relatedKey))->toArray();
        }

        list($idsOnly) = $this->getIdsWithAttributes($ids);

        $this->parent->fireModelEvent('pivotDetaching', true, $this->getRelationName(), $idsOnly);
        $parentResult = parent::detach($ids, $touch);

        if ($parentResult) {
            $this->parent->fireModelEvent('pivotDetached', false, $this->getRelationName(), $idsOnly);
        }

        return $parentResult;
    }
    public function detach($ids = null, $touch = true)
    {
        $wasCachable = $this->isCachable;
        $this->isCachable = false;

        try {
            $result = $this->detachParche($ids, $touch);
        } finally {
            $this->isCachable = $wasCachable;
        }

        if (! $this->isSyncing) {
            $this->flushCacheForPivotOperation();
        }

        return $result;
    }

    public function updateExistingPivot($id, array $attributes, $touch = true)
    {
        $wasCachable = $this->isCachable;
        $this->isCachable = false;

        try {
            $result = $this->traitUpdateExistingPivot($id, $attributes, $touch);
        } finally {
            $this->isCachable = $wasCachable;
        }

        if (! $this->isSyncing) {
            $this->flushCacheForPivotOperation();
        }

        return $result;
    }
}
