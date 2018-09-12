<?php


use \Caffeineinc\SilverStripeSiteTreeCache\CacheSiteTreeExtension;
use \SilverStripe\Core\Injector\Injector;
use \Psr\SimpleCache\CacheInterface;
use \SilverStripe\Core\Flushable;

/**
 *
 */
class CacheSiteTreeExtensionTest extends \SilverStripe\Dev\FunctionalTest
{
	protected static $use_draft_site = false;

	protected $usesDatabase = true;

	protected static $required_extensions = [
		"SilverStripe\CMS\Controllers\CMSMain" => CacheSiteTreeExtension::class
	];

	public function setUp()
	{
		parent::setUp();

		$this->useDraftSite(true);

		$this->logInWithPermission("ADMIN");

		// create a  sitetree
		for ($i = 0; $i < 10; $i++) {
			$page = new Page(['Title' => "Page $i"]);
			$id = $page->write();
		}
	}

	/**
	 * Ensure our cache key is updated if the sitetree changes (LastEdited)
	 */
	public function testSiteTreeCacheKey()
	{
		// get the original
		$cacheSiteTreeExtension = new CacheSiteTreeExtension();
		$cacheKey = $this->callProtectedMethod($cacheSiteTreeExtension, "getCacheKey");

		// add a new page
		$page = new Page(['Title' => "new page"]);
		$id = $page->write();

		// make sure the cache key has been updated.
		$newCacheKey = $this->callProtectedMethod($cacheSiteTreeExtension, "getCacheKey");
		$this->assertNotSame($cacheKey, $newCacheKey);
	}

	/**
	 * Ensure our cache key is updated if the member changes, and config is set.
	 */
	public function testSiteTreeCacheKeyMember()
	{
		// change setting to include the member id.
		$cacheSiteTreeExtension = new CacheSiteTreeExtension();
		CacheSiteTreeExtension::config()->merge("include_member_id", true);

		$cacheKey = $this->callProtectedMethod($cacheSiteTreeExtension, "getCacheKey");

		$member = $this->createMemberWithPermission("NEW PERMISSION");
		$this->logInAs($member);

		$newCacheKey = $this->callProtectedMethod($cacheSiteTreeExtension, "getCacheKey");
		$this->assertNotSame($cacheKey, $newCacheKey);

		// logout, and make no changes - key should change (note: this is just the cache key function
		// not the permissions to the module.
		$this->logOut();

		$loggedOutKey = $this->callProtectedMethod($cacheSiteTreeExtension, "getCacheKey");
		$this->assertNotSame($newCacheKey, $loggedOutKey);
	}

	/**
	 * We have to make sure
	 */
	public function testSiteTree()
	{
		$this->useDraftSite(true);

		// check to make sure sitetree is populating the cache as expected.
		$controller = $this->bootstrapController();

		$extension = new CacheSiteTreeExtension();
		$extension->setOwner($controller);

		$cache = Injector::inst()->get(CacheInterface::class . CacheSiteTreeExtension::CACHE_NAME);
		$cacheSiteTreeExtension = new CacheSiteTreeExtension();
		$cacheKey = $this->callProtectedMethod($cacheSiteTreeExtension, "getCacheKey");

		// inspect the cache and make sure it's empty
		$this->assertFalse($cache->has($cacheKey));

		$result = $extension->treeview();

		// Congratulations we're using the cache
		$cacheKey = $this->callProtectedMethod($cacheSiteTreeExtension, "getCacheKey");
		$cache = Injector::inst()->get(CacheInterface::class . CacheSiteTreeExtension::CACHE_NAME);

		$this->assertTrue($cache->has($cacheKey));

		$this->assertTrue(get_class($cache->get($cacheKey)) === get_class($result));
	}

	/**
	 * The cache should be able to be cleared by the flushable system.
	 */
	public function testCacheSiteTreeIsFlushable()
	{
		$extension = new CacheSiteTreeExtension();
		$this->assertInstanceOf(Flushable::class, $extension);
	}

	/**
	 * Helper function using reflection to call protected methods.
	 *
	 * @param $object
	 * @param $method
	 * @param array $args
	 *
	 * @return mixed
	 */
	public static function callProtectedMethod($object, $method, array $args = array())
	{
		$class = new ReflectionClass(get_class($object));
		$method = $class->getMethod($method);
		$method->setAccessible(true);
		return $method->invokeArgs($object, $args);
	}

	/**
	 * @return \SilverStripe\CMS\Controllers\CMSMain
	 */
	protected function bootstrapController(): \SilverStripe\CMS\Controllers\CMSMain
	{
		$request = new SilverStripe\Control\HTTPRequest('GET', '/admin/pages/edit/treeview');
		$request->setSession($this->session());
		$controller = new SilverStripe\CMS\Controllers\CMSMain();
		$controller->setRequest($request);

		$hintscache = Injector::inst()->get(CacheInterface::class . '.CMSMain_SiteTreeHints');
		$controller->setHintsCache($hintscache);
		$controller->pushCurrent();
		$controller->doInit();
		return $controller;
	}
}
