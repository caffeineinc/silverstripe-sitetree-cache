<?php

namespace Caffeineinc\CacheSiteTreeExtension;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Versioned\Versioned;

/**
 * Class CMSMainExtension
 * Cache the site tree until a descendant is updated.
 */
class CacheSiteTreeExtension extends DataExtension {

	const SITETREE_CACHE_TIME = 3600;

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
		switch ($action) {
			case "treeview":
				return $this->treeview();
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
        $cache = Injector::inst()->get(CacheInterface::class . '.SiteTree_Cache');
        $lastEdited = sha1(SiteTree::get()->sort("LastEdited DESC")->limit(1)->first()->LastEdited);

        $cacheKey = implode("-",
            [
                "treeview",
                $lastEdited,
                Versioned::get_reading_mode()
            ]
        );

        $myValue = $cache->get($cacheKey, false);
        if(!$myValue || $this->owner->getRequest()->getVar('flush')){
            $myValue = $this->owner->renderWith($this->owner->getTemplatesWithSuffix('_TreeView'));
            $cache->set($cacheKey, $myValue, self::SITETREE_CACHE_TIME);
        }

        return $myValue;
    }
}

