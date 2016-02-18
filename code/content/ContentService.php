<?php

/**
 * Entry point interface for accessing content related functionality
 * 
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ContentService {
	
	const SEPARATOR = ':||';

	protected $defaultStore;
	
	protected $stores = array(
		'File' => array(
			'ContentReader'		=> 'FileContentReader',
			'ContentWriter'		=> 'FileContentWriter',
		)
	);

	public function __construct($defaultStore = 'File') {
		$this->defaultStore = $defaultStore;
	}

	/**
	 * Set the list of stores available
	 *
	 * @param array $store 
	 */
	public function setStores($store) {
		$this->stores = $store;
	}
	
	/**
	 * Get the list of configured store types
	 *
	 * @return array
	 */
	public function getStoreTypes() {
		return $this->stores;
	}

	function getDefaultStore() {
		return $this->defaultStore;
	}

	/**
	 * Gets a writer for a DataObject
	 * 
	 * If the field already has a value, a writer is created matching that 
	 * identifier. Otherwise, a new writer is created based on either 
	 * 
	 * - The $type passed in 
	 * - whether the $object class specifies a prefered storage type via 
	 *   getEffectiveContentStore
	 * - what the `defaultStore` is set to for the content service
	 * 
	 *
	 * @param DataObject $object
	 *				The object to get a writer for
	 * @param String $field
	 *				The field being written to 
	 * @param String $type
	 *				Explicitly state what the content store type will be
	 * @return ContentWriter
	 */
	public function getWriterFor(DataObject $object = null, $field = 'FilePointer', $type = null) {
		if ($object && $field && $object->hasField($field)) {
			$val = $object->$field;
			if (strlen($val)) {
				$reader = $this->getReader($val);
				if ($reader && $reader->isReadable()) {
					return $reader->getWriter();
				}
			}
		}
		
		if (!$type) {
			// specifically expecting to be handling File objects, but allows other 
			// objects to play too
			if ($object && $object->hasMethod('getEffectiveContentStore')) {
				$type = $object->getEffectiveContentStore();
			} else {
				$type = $this->defaultStore;
			}
		}
		
		// looks like we're getting a writer with no underlying file (as yet)
		return $this->getWriter($type);
	}

	/**
	 *
	 * @param string $identifier
	 *				Identifier in the format type://uniqueid
	 * @return ContentReader
	 */
	public function getReader($identifier = null) {
		return $this->createReaderWriter($identifier, 'ContentReader');
	}
	
	/**
	 * @param string $identifier
	 * @return ContentWriter
	 */
	public function getWriter($identifier = null) {
		return $this->createReaderWriter($identifier, 'ContentWriter');
	}
	
	/**
	 * Handles creation of a reader/writer
	 *
	 * @param string $identifier
	 * @param string $readwrite
	 * @return cls 
	 */
	protected function createReaderWriter($identifier, $readwrite) {
		$id = null;
		//if we aren't passed an identifier use the defaultStore
		$identifier = $identifier == null ? $this->defaultStore : $identifier;

		if (strpos($identifier, self::SEPARATOR)) {
			list($type, $id) = explode(self::SEPARATOR, $identifier);
		} else {
			$type = $identifier;
		}

		if (!$type) {
			throw new Exception("Invalid content store type $type");
		}
		
		if (isset($this->stores[$type])) {
			$class = $this->stores[$type][$readwrite];
			return Injector::inst()->create($class, $id, $type);
		} else {
			$cls = $type . $readwrite;
			if (class_exists($cls)) {
				return new $cls($id, $type);
			}
		}
	}
	
	/**
	 * Gets a content reader for the given store type over the asset given in 
	 * assetName. This is used for finding if an asset is stored remotely or 
	 * not
	 * 
	 * Returns NULL if that asset doesn't exist. 
	 *
	 * @param string $storeType
	 *				The named store we're looking into
	 * @param string $assetName 
	 *				The name of the asset to look up
	 * @param boolean $remapToId
	 *				Do we let the reader remap the name to how it represents asset paths? Or are 
	 *				we looking up an already-mapped path name?
	 * 
	 * @return ContentReader
	 */
	public function findReaderFor($storeType, $assetName, $remapToId = true) {
		$writer = $this->getWriter($storeType);
		$contentId = $storeType . self::SEPARATOR . ($remapToId ? $writer->nameToId($assetName) : $assetName);
		$reader = $this->getReader($contentId);
		return $reader ? ($reader->isReadable() ? $reader : null) : null;
	}
}
