<?php namespace DominoPOS\OrbitACL;
/**
 * Library for providing Access Control List (ACL) used by Orbit Application.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitACL\Exception\ACLUnauthenticatedException;
use Illuminate\Support\Facades\Config;
use Permission;
use Zend\Permissions\Acl\Acl as ZendACL;

class ACL
{
    /**
     * Name of the Role
     *
     * @var string
     */
    public $roleName = '';

    /**
     * Super Admin role name. This role has no restriction.
     *
     * @var string
     */
    public $superAdminRoleName = 'Super Admin';

    /**
     * Instance of the User model object
     *
     * @var User
     */
    public $user = NULL;

    /**
     * List of role permissions.
     *
     * @var array
     */
    public $rolePermissions = array();

    /**
     * List of custom permissions that user has.
     *
     * @var array
     */
    public $customPermissions = array();

    /**
     * List of global permission that Orbit Application had.
     *
     * @var array
     */
    public $globalPermissions = array();

    /**
     * List of final permissions which applied to the user. It is combination of
     * custom permission, role permission and global permission.
     *
     * Custom permission has highest priority, Role permission comes second and
     * the lowest priority is global permission.
     *
     * It means that if all of those three has same permission name then the one
     * that would be picked up is the one that has highest priority.
     *
     * @var array
     */
    public $finalPermissions = array();

    /**
     * Instance of the Zend ACL
     *
     * @var Zend\Permissions\Acl\Acl
     */
    public $acl = NULL;

    /**
     * Constructor
     *
     * @param $user User - The User instance
     * @return void
     */
    public function __construct($user=NULL)
    {
        if (is_object($user)) {
            $this->bootPermissions($user);
        }
    }

    /**
     * Static method to create OrbitACL instance.
     *
     * @param $user User - The User instance
     * @return ACL
     */
    public static function create($user=NULL)
    {
        return new static($user);
    }

    /**
     * Fill the final permissions.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param $user User - The User instance
     * @return ACL
     */
    public function bootPermissions($user)
    {
        // Get all the users role and permissions by accessing it by property
        // not eloquent style to prevent unnecessary queris
        foreach ($user->role->permissions as $perm) {
            $this->rolePermissions[$perm->permission_name] = $perm->pivot->allowed;
        }

        foreach ($user->permissions as $perm) {
            $this->customPermissions[$perm->permission_name] = $perm->pivot->allowed;
        }

        // Cache it in memory
        $globalCachePermissions = function()
        {
            $perms = Config::get('cache.permissions.all');

            if (! is_array($perms)) {
                $perms = Permission::all();
                Config::set('cache.permissions.all', $perms);
            }

            return $perms;
        };

        foreach ($globalCachePermissions() as $perm) {
            $attributes = $perm->getAttributes();
            if (isset($attributes['permission_default_value'])) {
                $this->globalPermissions[$perm->permission_name] = $perm->permission_default_value;
            } else {
                $this->globalPermissions[$perm->permission_name] = 'no';
            }
        }

        // Combine all the permissions and its priority
        $this->finalPermissions = $this->customPermissions +
                                  $this->rolePermissions +
                                  $this->globalPermissions;

        $this->roleName = $user->role->role_name;

        $this->acl = new ZendACL();
        $this->acl->addRole($this->roleName);

        if ($user->status === 'blocked') {
            return $this;
        }

        if ($this->roleName === $this->superAdminRoleName) {
            return $this;
        }

        foreach ($this->finalPermissions as $perm=>$allowed) {
            if ($allowed === 'yes') {
                $this->acl->allow($this->roleName, NULL, $perm);
            }
        }

        return $this;
    }

    /**
     * Method to determine whether particular permission is allowed or not
     * for current user.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $permission - Permission name to check
     * @return boolean
     */
    public function isAllowed($permission)
    {
        if ($this->roleName === $this->superAdminRoleName) {
            // I'm the man, I rule them all
            return TRUE;
        }

        return $this->acl->isAllowed($this->roleName, NULL, $permission);
    }

    /**
     * Method to clear global cache permissions.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    public function clearGlobalPermissionCache()
    {
        Config::set('cache.permissions.all', NULL);
    }

    /**
     * Throw a forbidden exception.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $message - Forbidden access message
     * @return void
     * @throw DominoPOS\OrbitACL\Exception\ACLForbiddenException
     */
    public static function throwAccessForbidden($message = "You don't have permission to access specified resource.")
    {
        throw new ACLForbiddenException($message);
    }

    /**
     * Throw an unauthenticated user request.
     *
     * @author Budi <budi@gotomalls.com>
     * @param  string $message exception message.
     * @throws  ACLUnauthenticatedException
     */
    public static function throwUnauthenticatedRequest($message = 'You have to login to continue.')
    {
        throw new ACLUnauthenticatedException($message);
    }
}
