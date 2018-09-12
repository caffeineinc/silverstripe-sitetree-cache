# silverstripe-sitetree-cache

Tired of slow site tree loading times. Wait no more! 

- Caches large site trees with a customisable TTL through config. 
- Can be shared across all admins (config setting)

## requirements

* SilverStripe 4 + 
 
## Installing

```sh
$ composer require caffeineinc/silverstripe-sitetree-cache
```

## Using

Should work out of the box, customise the configuration settings to change the performance of site. 

* cache_time 
the TTL for the cache in seconds - default 3600 
* include_member_id 
If you know what your doing, and you know you can share you're site tree with all admins, you can set this to false and 
share a cache key amongst all admins. 

This module also sets node threadsholds fairly low, so you don't have to load the entire sitetree. You can adjust these 
seperately `node_threshold_total` and `node_threshold_leaf` but see Hierarchy and MarkedSet for more details. 

```yml
Caffeineinc\SilverStripeSiteTreeCache\CacheSiteTreeExtension:
  cache_time: 86400
  include_member_id: false

SilverStripe\CMS\Model\SiteTree:
  node_threshold_total: 5
  node_threshold_leaf: 10

SilverStripe\Forms\TreeDropdownField:
  node_threshold_total: 20
  node_threshold_leaf: 10
```
