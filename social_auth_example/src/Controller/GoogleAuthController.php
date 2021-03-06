<?php

namespace Drupal\social_auth_example\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\social_auth_example\GoogleAuthManager;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\SocialAuthUserManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Zend\Diactoros\Response\RedirectResponse;

/**
 * Manages requests to Google API.
 *
 * Most of the code here is specific to implement a Google login process. Social
 * Networking services might require different approaches. Use this class as
 * an example of how to implement your specific module.
 */
class GoogleAuthController extends ControllerBase {

  /**
   * The network plugin manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  private $networkManager;

  /**
   * The Google authentication manager.
   *
   * @var \Drupal\social_auth_example\GoogleAuthManager
   */
  private $googleManager;

  /**
   * The user manager.
   *
   * @var \Drupal\social_auth\SocialAuthUserManager
   */
  private $userManager;

  /**
   * The session manager.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * GoogleLoginController constructor.
   *
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Used to get an instance of social_auth_google network plugin.
   * @param \Drupal\social_auth\SocialAuthUserManager $user_manager
   *   Manages user login/registration.
   * @param \Drupal\social_auth_example\GoogleAuthManager $google_manager
   *   Used to manage authentication methods.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   Used to store the access token into a session variable.
   */
  public function __construct(NetworkManager $network_manager, SocialAuthUserManager $user_manager, GoogleAuthManager $google_manager, SessionInterface $session) {
    $this->networkManager = $network_manager;
    $this->googleManager = $google_manager;
    $this->userManager = $user_manager;
    $this->session = $session;

    // Sets the plugin id.
    $this->userManager->setPluginId('social_auth_example');

    // Sets the session keys to nullify if user could not logged in.
    $this->userManager->setSessionKeysToNullify(['social_auth_example_access_token']);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('plugin.network.manager'),
        $container->get('social_auth.user_manager'),
        $container->get('example_auth.manager'),
        $container->get('session')
    );
  }

  /**
   * Redirects to Google Services Authentication page.
   *
   * Most of the Social Networks' API require you to redirect users to a
   * authentication page. This method is not a mandatory one, instead you must
   * adapt to the requirements of the module you are implementing.
   *
   * This method is called in 'social_auth_example.redirect_to_google' route.
   *
   * @see social_auth_example.routing.yml
   *
   * This method is triggered when the user loads user/login/google. It creates
   * an instance of the Network Plugin 'social auth google' and returns an
   * instance of the \Google_Client object.
   *
   * It later sets the permissions that should be asked for, and redirects the
   * user to Google Accounts to allow him to grant those permissions.
   *
   * After the user grants permission, Google redirects him to a url specified
   * in the Google project settings. In this case, it should redirects to
   * 'user/login/google/callback', which calls the callback method.
   *
   * @return \Zend\Diactoros\Response\RedirectResponse
   *   Redirection to Google Accounts.
   */
  public function redirectToGoogle() {
    /* @var \Google_Client $client */
    // Creates an instance of the Network Plugin and gets the SDK.
    $client = $this->networkManager->createInstance('social_auth_example')->getSdk();
    // Sets the scopes (permissions to ask for).
    $client->setScopes(['email', 'profile']);

    // Redirects to Google Accounts to allow the user grant the permissions.
    return new RedirectResponse($client->createAuthUrl());
  }

  /**
   * Callback function to login user.
   *
   * Most of the Social Networks' API redirects to callback url. This method is
   * not a mandatory one, instead you must adapt to the requirements of the
   * module you are implementing.
   *
   * This method is called in 'social_auth_example.callback' route.
   *
   * @see social_auth_example.routing.yml
   *
   * This method is triggered when the path user/login/google/callback is
   * loaded. It creates an instance of the Network Plugin 'social auth example'.
   *
   * It later authenticates the user and creates the service to obtain data
   * about the user.
   */
  public function callback() {
    /* @var \Google_Client $client */
    // Creates the Network Plugin instance and get the SDK.
    $client = $this->networkManager->createInstance('social_auth_example')->getSdk();

    // Authenticates the user.
    $this->googleManager->setClient($client)->authenticate();

    // Saves access token so that event subscribers can call Google API.
    $this->session->set('social_auth_example_access_token', $this->googleManager->getAccessToken());

    // Gets user information.
    $user = $this->googleManager->getUserInfo();
    // If user information could be retrieved.
    if ($user) {
      // Returns the redirect value obtained from authenticateUser.
      return $this->userManager->authenticateUser($user->getEmail(), $user->getName(), $user->getId(), $user->getPicture());
    }

    drupal_set_message($this->t('You could not be authenticated, please contact the administrator'), 'error');
    return $this->redirect('user.login');
  }

}
