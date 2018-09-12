<?php

namespace Caffeineinc\SilverStripeSiteTreeCache;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;

/**
 * Class CMSMainExtension
 * Cache the site tree until a descendant is updated.
 */
class CacheSiteTreeExtension extends DataExtension implements Flushable
{
	use Configurable;

	/**
	 * @config
	 *
	 * The cache time to live for the site tree (defaults to one hour).
	 *
	 * @var int cache ttl
	 */
	private static $cache_time = 3600;

	/**
	 * Overload the call handler to return our action instead (cached)
	 *
	 * @param $request
	 * @param $action
	 *
	 * @return DBHTMLText
	 */
	public function beforeCallActionHandler($request, $action)
	{
		// Currently only the tree view action is implemented.
		switch ($action) {
			case "treeview":
				return $this->treeview();
			default:
				return;
		}
	}

	/**
	 * This method exclusively handles deferred ajax requests to render the
	 * pages tree deferred handler (no pjax-fragment) but unlike it's parent,
	 * it caches the result for an hour.
	 *
	 * @return DBHTMLText HTML response with the rendered treeview
	 */
	public function treeview()
	{
		// Last edited applies to current reading mode.
		$lastEdited = sha1(SiteTree::get()->sort("LastEdited DESC")->limit(1)->first()->LastEdited);

		$cacheKey = implode("-",
			[
				"treeview",
				$lastEdited,
				Versioned::get_reading_mode()
			]
		);

		// check our cache to see if we've already got an entry.
		$cache = Injector::inst()->get(CacheInterface::class . '.SiteTree_Cache');

		// If we've got a site tree in the cache, use it.
		$siteTree = $cache->get($cacheKey, false);
		if ($siteTree) {
			return $siteTree;
		}

		// Render the original treeview as normal and save it to our cache.
		$siteTree = $this->owner->renderWith($this->owner->getTemplatesWithSuffix('_TreeView'));
		$cache->set($cacheKey, $siteTree, $this->config()->get('cache_time'));

		return $siteTree;
	}

	/**
	 * Destroy all cache entries on a flush.
	 */
	public static function flush()
	{
		$cache = Injector::inst()->get(CacheInterface::class . '.SiteTree_Cache');
		$cache->clear();
	}
}

