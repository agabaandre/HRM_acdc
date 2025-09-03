<style>
html {
  font-size: 14px;
}
body {
  background: #f6f9fc;
  font-family: "Open Sans", sans-serif;
  color: #525f7f;
}
h2{
  margin: 5%;
  text-align: center;
  font-size: 2rem;
  font-weight: 100;
}
.timeline {
  display: flex;
  flex-direction: column;
  width: 50vw;
  margin: 5% auto;

  &__event {
    background: #fff;
    margin-bottom: 20px;
    position: relative;
    display: flex;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 30px 60px -12px rgba(50, 50, 93, 0.25),
      0 18px 36px -18px rgba(0, 0, 0, 0.3),
      0 -12px 36px -8px rgba(0, 0, 0, 0.025);

    &__title {
      font-size: 1.2rem;
      line-height: 1.4;
      text-transform: uppercase;

      font-weight: 600;
      color: #9251ac;
      letter-spacing: 1.5px;
    }
    &__content {
      padding: 20px;
    }
    &__date {
      color: #f6a4ec;
      font-size: 1.5rem;
      font-weight: 600;
      white-space: nowrap;
    }
    &__icon {
      border-radius: 8px 0 0 8px;
      background: #9251ac;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-basis: 40%;
      font-size: 2rem;
      color: #9251ac;
      padding: 20px;

      i {
        position: absolute;
        top: 50%;
        left:-65px;
        font-size: 2.5rem;
      transform: translateY(-50%);
        
      }
    }
    &__description {
      flex-basis: 60%;
    }

    &:after {
      content: "";
      width: 2px;
      height: 100%;
      background: #9251ac;
      position: absolute;
      top: 52%;
      // transform: translateY(-50%);
      left: -3.5rem;
      z-index: -1;
      
    
    }

    &:before {
      content: "";
      width: 5rem;
      height: 5rem;
      position: absolute;
      background: #f6a4ec;
      border-radius: 100%;
      left: -6rem;
      // right: 90%;
      top: 50%;
      transform: translateY(-50%);
      border: 2px solid #9251ac;
    }
    &--type2 {
      &:before {
        background: #87bbfe;
        border-color: #555ac0;
      }
      &:after{
        background: #555ac0;
  
      }
      .timeline__event__date {
        color: #87bbfe;
      }

      .timeline__event__icon {
        background: #555ac0;
        color: #555ac0;
      }
      .timeline__event__title {
        color: #555ac0;
      }
      .timeline__event__title {
      }
    }

    &--type3 {
      &:before {
        background: #aff1b6;
        border-color: #24b47e;
      }
      &:after{
        background: #24b47e;
  
      }
      .timeline__event__date {
        color: #aff1b6;
      }

      .timeline__event__icon {
        background: #24b47e;
        color: #24b47e;
      }
      .timeline__event__title {
        color: #24b47e;
      }
      .timeline__event__title {
      }
    }
      &:last-child{

        &:after{
          content: none;
        }
    }
    
  }
}

@media (max-width: 786px) {
  .timeline__event {
    flex-direction: column;
  }
  .timeline__event__icon {
    border-radius: 4px 4px 0 0;
  }
}
</style>

<div class="timeline">
  <div class="timeline__event  animated fadeInUp delay-3s timeline__event--type1">
    <div class="timeline__event__icon ">
      <i class="lni-cake"></i>
      <div class="timeline__event__date">
        20-08-2019
      </div>
    </div>
    <div class="timeline__event__content ">
      <div class="timeline__event__title">
        Birthday
      </div>
      <div class="timeline__event__description">
        <p>Lorem, ipsum dolor sit amet consectetur adipisicing elit. Vel, nam! Nam eveniet ut aliquam ab asperiores, accusamus iure veniam corporis incidunt reprehenderit accusantium id aut architecto harum quidem dolorem in!</p>
      </div>
    </div>
  </div>
  <div class="timeline__event animated fadeInUp delay-2s timeline__event--type2">
    <div class="timeline__event__icon">
      <i class="lni-burger"></i>
      <div class="timeline__event__date">
        20-08-2019
      </div>
    </div>
    <div class="timeline__event__content">
      <div class="timeline__event__title">
        Lunch
      </div>
      <div class="timeline__event__description">
        <p>Lorem, ipsum dolor sit amet consectetur adipisicing elit. Vel, nam! Nam eveniet ut aliquam ab asperiores, accusamus iure veniam corporis incidunt reprehenderit accusantium id aut architecto harum quidem dolorem in!</p>
      </div>
    </div>
  </div>
  <div class="timeline__event animated fadeInUp delay-1s timeline__event--type3">
    <div class="timeline__event__icon">
      <i class="lni-slim"></i>
      <div class="timeline__event__date">
        20-08-2019
      </div>
    </div>
    <div class="timeline__event__content">
      <div class="timeline__event__title">
        Exercise
      </div>
      <div class="timeline__event__description">
        <p>Lorem, ipsum dolor sit amet consectetur adipisicing elit. Vel, nam! Nam eveniet ut aliquam ab asperiores, accusamus iure veniam corporis incidunt reprehenderit accusantium id aut architecto harum quidem dolorem in!</p>
      </div>

    </div>
  </div>
  <div class="timeline__event animated fadeInUp timeline__event--type1">
    <div class="timeline__event__icon">
      <i class="lni-cake"></i>
      <div class="timeline__event__date">
        20-08-2019
      </div>
    </div>
    <div class="timeline__event__content">
      <div class="timeline__event__title">
        Birthday
      </div>
      <div class="timeline__event__description">
        <p>Lorem, ipsum dolor sit amet consectetur adipisicing elit. Vel, nam! Nam eveniet ut aliquam ab asperiores, accusamus iure veniam corporis incidunt reprehenderit accusantium id aut architecto harum quidem dolorem in!</p>
      </div>
    </div>
  </div>

</div>