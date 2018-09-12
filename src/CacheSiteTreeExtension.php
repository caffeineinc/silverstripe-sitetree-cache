<?php

namespace Caffeineinc\SilverStripeSiteTreeCache;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;

/**
 * Class CMSMainExtension
 * Cache the site tree until a descendant is updated.
 */
class CacheSiteTreeExtension extends DataExtension implements Flushable
{
	CONST CACHE_NAME = ".SiteTree_Cache";

	use Configurable;

	/**
	 *
	 * @config
	 *
	 * The cache time to live for the site tree (defaults to one hour).
	 *
	 * @var int cache ttl
	 */
	private static $cache_time = 3600;

	/**
	 * @config
	 *
	 * If you need member differentiation in sitetree (e.g. security, or hidden sections) then you have to include the
	 * current member ID for security, otherwise they'd see the cached content from the other. The cost is, it's
	 * slightly less performant (less cache hits).
	 *
	 * @var bool
	 */
	private static $include_member_id = false;

	/**
	 * Build a cache key suitable for the SiteTree.
	 * @return string
	 */
	protected static function getCacheKey(): string
	{
		// Last edited applies to current reading mode.
		$lastEdited = sha1(SiteTree::get()->sort("LastEdited DESC")->limit(1)->first()->LastEdited);

		$cacheKey = implode("-",
			[
				"treeview",
				SiteTree::get()->count(),
				$lastEdited,
				Versioned::get_reading_mode() ? Versioned::get_reading_mode(): "version-not-set"
			]
		);

		if (self::config()->get('include_member_id') && Security::getCurrentUser()) {
			$cacheKey .= "-" . Security::getCurrentUser()->ID;
		}
		return $cacheKey;
	}

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
		$cacheKey = self::getCacheKey();

		// check our cache to see if we've already got an entry.
		$cache = Injector::inst()->get(CacheInterface::class . static::CACHE_NAME);

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
		$cache = Injector::inst()->get(CacheInterface::class . static::CACHE_NAME);
		$cache->clear();
	}
}

