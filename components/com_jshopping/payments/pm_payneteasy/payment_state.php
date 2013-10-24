<?php

class jshopPaymentState extends JTable
{
    public function __construct($dbDriver)
    {
        parent::__construct('#__jshopping_payment_method_payneteasy', 'client_id', $dbDriver);
    }

    /**
     * Same as store() method, but always execute INSERT query.
     *
     * @see store()
     *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean  True on success.
     */
    public function create($updateNulls = false)
    {
		$k = $this->_tbl_key;

		// Implement JObservableInterface: Pre-processing by observers
		$this->_observers->update('onBeforeStore', array($updateNulls, $k));

		if (!empty($this->asset_id))
		{
			$currentAssetId = $this->asset_id;
		}

		if (0 == $this->$k)
		{
			$this->$k = null;
		}

		// The asset id field is managed privately by this class.
		if ($this->_trackAssets)
		{
			unset($this->asset_id);
		}

        $result = $this->_db->insertObject($this->_tbl, $this, $this->_tbl_key);

		// If the table is not set to track assets return true.
		if ($this->_trackAssets)
		{

			if ($this->_locked)
			{
				$this->_unlock();
			}

			/*
			 * Asset Tracking
			 */

			$parentId = $this->_getAssetParentId();
			$name = $this->_getAssetName();
			$title = $this->_getAssetTitle();

			$asset = self::getInstance('Asset', 'JTable', array('dbo' => $this->getDbo()));
			$asset->loadByName($name);

			// Re-inject the asset id.
			$this->asset_id = $asset->id;

			// Check for an error.
			$error = $asset->getError();
			if ($error)
			{
				$this->setError($error);
				$result = false;
			}
			else
			{
				// Specify how a new or moved node asset is inserted into the tree.
				if (empty($this->asset_id) || $asset->parent_id != $parentId)
				{
					$asset->setLocation($parentId, 'last-child');
				}

				// Prepare the asset to be stored.
				$asset->parent_id = $parentId;
				$asset->name = $name;
				$asset->title = $title;

				if ($this->_rules instanceof JAccessRules)
				{
					$asset->rules = (string) $this->_rules;
				}

				if (!$asset->check() || !$asset->store($updateNulls))
				{
					$this->setError($asset->getError());
					$result = false;
				}
				else
				{
					// Create an asset_id or heal one that is corrupted.
					if (empty($this->asset_id) || ($currentAssetId != $this->asset_id && !empty($this->asset_id)))
					{
						// Update the asset_id field in this table.
						$this->asset_id = (int) $asset->id;

						$query = $this->_db->getQuery(true)
							->update($this->_db->quoteName($this->_tbl))
							->set('asset_id = ' . (int) $this->asset_id)
							->where($this->_db->quoteName($k) . ' = ' . (int) $this->$k);
						$this->_db->setQuery($query);

						$this->_db->execute();
					}
				}
			}
		}

		// Implement JObservableInterface: Post-processing by observers
		$this->_observers->update('onAfterStore', array(&$result));

		return $result;
    }
}

